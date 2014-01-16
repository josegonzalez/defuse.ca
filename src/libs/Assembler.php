<?php

class InvalidModeException extends Exception {}
class UnsafeCodeException extends Exception {}
class AssemblyFailureException extends Exception {}

class Assembler
{
    private $archTick = "-m32";

    public function setArch($arch)
    {
        if ($arch == "x86") {
            $this->archTick = "-m32";
        } elseif ($arch == "x64") {
            $this->archTick = "-m64";
        } else {
            throw new InvalidModeException("$arch is not a valid mode.");
        }
    }

    public function assemble($code)
    {
        // Make sure the input is a reasonable size and safe to compile.
        if (strlen($code) < 10 * 1024 && $this->isSafeCode($code)) {
            // Random (hopefully unique) temporary file names.
            $tempnam = "/tmp/" . rand();
            // WARNING: So that GCC doesn't pipe the file through CPP (processing 
            // macros, etc), the extension must be .s (lowercase) not .S (uppercase).
            $source_path = $tempnam . ".s";
            $obj_path = $tempnam . ".o";

            // Write the assembly source code.
            $asmfile = ".intel_syntax noprefix\n_main:\n" . $code . "\n";
            file_put_contents($source_path, $asmfile);

            $ret = 1;
            $output = array();

            // Assemble the source with gcc.
            exec("gcc $this->archTick -c $source_path -o $obj_path 2>&1", $output, $ret);

            if ($ret == 0)
            {
                // Use objdump to disassemble it.
                exec("objdump -M intel -d $obj_path", $output, $ret);

                if ($ret == 0)
                {
                    $strout = implode("\n", $output);
                    if (file_exists($source_path)) { unlink($source_path); }
                    if (file_exists($obj_path)) { unlink($obj_path); }
                    return $this->buildStructuredOutput($strout);
                }
                else
                {
                    if (file_exists($source_path)) { unlink($source_path); }
                    if (file_exists($obj_path)) { unlink($obj_path); }
                    throw new AssemblyFailureException("Something went wrong!");
                }
            }
            else
            {
                $strout = implode("\n", $output);
                $strout = preg_replace('/\\/tmp\\/\\d+\\.s:(\d+:|)\s*/', "", $strout);
                $strout = str_replace("Assembler messages:\n", "", $strout);
                if (file_exists($source_path)) { unlink($source_path); }
                if (file_exists($obj_path)) { unlink($obj_path); }
                throw new AssemblyFailureException($strout);
            }

        } else {
            throw new UnsafeCodeException();
        }
    }

    private function isSafeCode($code)
    {
        // Whitelist 'safe' directives. A list of directives can be found here:
        // http://sourceware.org/binutils/docs-2.23.1/as/Pseudo-Ops.html#Pseudo-Ops
        // Notes:
        //  - .fill et al. are deemed unsafe because of potential DoS (huge output).
        $safe_directives = array(
            ".ascii", ".asciz", ".align", ".balign",
            ".byte", ".int", ".double", ".quad", ".octa", ".word"
        );

        foreach ($safe_directives as $directive)
            $code = str_replace($directive, "", $code);

        // These comments have special meaning to as. They don't seem dangerous but
        // reject anyway to be safe.
        if (strpos($code,"#NO_APP",0) !== false || strpos($code,"#APP",0) !== false)
            return false;

        // If the source contains any other directives, reject.
        // Yes, this will make it fail if there's a non-directive period in a string
        // constant, but if we want to check for that safely we need to go beyond
        // strpos and even regular expressions.
        return strpos($code, ".", 0) === false; 
    }

    private function buildStructuredOutput($objdump_output)
    {
        $output = array();

        // Find where the actual code starts
        $code_start = strpos($objdump_output, "<_main>:\n");
        if ($code_start < 0) { 
            throw new AssemblyFailureException("Something went wrong.");
        }
        $code_start += strlen("<_main>:\n");

        // Extract just the code.
        $code = substr($objdump_output, $code_start);
        $code = preg_replace('/(\\n|^)\\s*/', "\n", $code);
        $code = trim($code);

        $justBytes = "";
        $lines = explode("\n", $code);
        foreach ($lines as $line)
        {
            $matches = array();
            // Note: there might be a bug here if the mnemonic is equal to a hex byte.
            // The negative lookahead for a colon is to filter out lables, e.g.
            // "00000013 <location1>:"
            $res = preg_match('/([a-fA-F0-9]{2}(\s+|$))+(?!.*:)/', $line, $matches);
            // Ignore the line if it doesn't have the expected run of hex digits.
            if ($res == 0 || $res == false)
                continue;
            $justBytes .= $matches[0];
        }

        $justBytes = strtoupper($justBytes);
        $justBytes = str_replace("00", "ZERO", $justBytes);
        $justBytes = str_replace(" ", "", $justBytes);
        $justBytes = str_replace("\t", "", $justBytes);
        $safe_justBytes = htmlentities($justBytes, ENT_QUOTES);
        $safe_justBytes = str_replace("ZERO", "<b>00</b>", $safe_justBytes);
        $justBytes = str_replace("ZERO", "00", $justBytes);

        $byteString = "";
        $arrayDef = "{";
        for ($i = 0; $i < strlen($justBytes); $i+=2)
        {
            $hex = substr($justBytes, $i, 2);
            $byteString .= "\x" . $hex;
            $arrayDef .= " 0x" . $hex;
            if ($i + 2 < strlen($justBytes))
                $arrayDef .= ",";
        }
        $arrayDef .= " }";

        $output['code'] = $code;
        $output['hex'] = $justBytes;
        $output['hex_zero_bold'] = $safe_justBytes;
        $output['string'] = $byteString;
        $output['array'] = $arrayDef;

        return $output;
    }
}

?>
