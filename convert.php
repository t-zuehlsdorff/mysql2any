<?php
/**
 * Created by PhpStorm.
 * User: c.basten
 * Date: 17.11.2016
 * Time: 12:58
 */

class Convert{

//Main function
//read filename give value
    function main_mysql2any($sqlFile)
    {
        $sqlFile = $this->checkFile($sqlFile);

        if ($sqlFile != NULL){
            file_put_contents("convert_" . $sqlFile , $this->convertFile($sqlFile));
        }

        print("ende \n");

    }

//give ending
    function endsWith($haystack, $needle)
    {
        $length = mb_strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (mb_substr($haystack, -$length) === $needle);
    }

//check file ending and exist
    function checkFile($sqlFile)
    {
        if (!file_exists($sqlFile) OR !$this->endsWith($sqlFile, '.sql')){
            print($sqlFile. "file not a .sql, not available or not exist!");
            $sqlFile = NULL;
            return $sqlFile;
        }
        return $sqlFile;
    }

//List with all wrong items
    function wrongItems()
    {
        $item = array('`', 'COMMENT','int(', 'unsigned', 'AUTO_INCREMENT', 'ENGINE', 'KEY');
        return $item;
    }

//convert CREATE TABLE lines
    function convertFile_create($content){

        $item = $this->wrongItems();
        foreach ($content as $line_num => $line)
        {
            $line = trim($line);
            $line_cache = $line;
            foreach ($item as $item_num => $item_value)
            {
                if (mb_strstr($line,$item_value,FALSE) != FALSE)
                {
                    var_dump($item_value);
                    var_dump($line);

                    //Remove `` in tablename, columnname etc
                    if($item_value == '`'){
                        $line = str_replace('`','',$line);
                    }

                    // Remove all unsigned
                    if($item_value == 'unsigned'){
                        $line = str_replace('unsigned',' ',$line);
                    }

                    // Remove al AUTO_INCREMENT
                    if($item_value == 'AUTO_INCREMENT'){
                        $line = str_replace('AUTO_INCREMENT',' ',$line);
                    }

                    //ALL Xint(n) int integer
                    if($item_value == 'int('){
                        for($n = 0; $n <= 30; $n++){
                            $line = str_replace('small' . $item_value . $n . ')','Integer',$line);
                            $line = str_replace($item_value . $n . ')','Integer',$line);
                        }
                    }

                    //Remove all COMMENT
                    if($item_value == 'COMMENT'){
                        $cache_value = mb_strstr($line,$item_value,FALSE); //from ELEMENT to ENDE
                        $cache_value_last = NULL;
                        if((mb_strstr($cache_value, ';', FALSE)) != FALSE){
                            $cache_value_last = mb_strstr($cache_value, ';', FALSE);
                        }elseif((mb_strstr($cache_value, ',', FALSE)) != FALSE){
                            $cache_value_last = mb_strstr($cache_value, ',', FALSE);
                        }
                        $line = mb_strstr($line,$cache_value,TRUE). $cache_value_last;
                    }

                    //Convert KEY to FOREIGN KEY
                    if($item_value == 'KEY'){
                        if((mb_strstr($line, 'PRIMARY')) == FALSE){
                            if((mb_strstr($line, 'CONSTRAINT')) == FALSE){

                                $cache_value_columnname = mb_strstr($line,')',TRUE);
                                $cache_value_columnname = mb_strstr($cache_value_columnname,'(',FALSE);
                                $cache_value_columnname = str_replace('(','',$cache_value_columnname);

                                $cache_value_tablename = mb_strstr($line,'(',TRUE);
                                $cache_value_tablename = str_replace($item_value,'',$cache_value_tablename);
                                $cache_value_tablename = str_replace('_'.$cache_value_columnname,'',$cache_value_tablename);
                                $cache_value_tablename = str_replace(' ','',$cache_value_tablename);

                                var_dump($cache_value_columnname);
                                var_dump($cache_value_tablename);

                                $line = 'FOREIGN KEY ' . $cache_value_tablename.'('.$cache_value_columnname.')';
                            }
                        }
                    }

                    //Remove all CHARSET AND STANDARD
                    if($item_value == 'ENGINE'){
                        $line = ");";
                        $content[$line_num - 1] = str_replace(',', '',$content[$line_num - 1]);
                    }
                    $content[$line_num] = $line.'   /* CONVERTED: '.$line_cache.'*/'."\n";
                    var_dump($line);
                }
            }
        }
        return $content;
    }

//convert lines to standard
    function convertFile($sqlFile)
    {
        $sqlFile_content = file($sqlFile);
        $sqlFile_content = $this->convertFile_create($sqlFile_content);

        var_dump($sqlFile_content);

        return $sqlFile_content;
    }
}

$convert = new Convert;
var_dump($argv[1]);
$convert->main_mysql2any($argv[1]);

