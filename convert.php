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
    function main_mysql2any($sqlFilename)
    {
        $sqlFilename = $this->checkFile($sqlFilename);

        if ($sqlFilename != NULL){
            $this->convertFile($sqlFilename);
        }

        print("\nende\n");
    }

//check file ending and exist
    function checkFile($sqlFilename)
    {
        if (is_writable($sqlFilename)) {

            if (!$handle = fopen($sqlFilename, "r+")) {
                echo "Kann die Datei $sqlFilename nicht öffnen";
                return FALSE;
            }

            fclose($handle);

        } else {
            echo "Die Datei $sqlFilename ist nicht schreibbar";
            return FALSE;
        }

        return $sqlFilename;
    }

//List with all wrong items
    function wrongItems()
    {
        $item = array('`', 'COMMENT','int(', 'unsigned', 'AUTO_INCREMENT', 'ENGINE', 'KEY', ';');
        return $item;
    }

    //convert CREATE TABLE lines
    //create a comment with the original statement on the end of a line
    function convertFile_create($content){

        $item = $this->wrongItems();
        $index = array();      //for creating the 'CREATE INDEX' statements
        $comment = array();     //for creating the 'COMMENT' statements
        $tablename = NULL;

        foreach ($content as $line_num => $line)
        {
            $line = trim($line);

            //extract the tablename from CREATE TABLE
            if(mb_strstr($line,'CREATE TABLE',FALSE) != FALSE)
            {
                $tablename = mb_strstr($line,'`',FALSE);
                $tablename = mb_strstr($tablename,'(',TRUE);
                $tablename = str_replace('`','',$tablename);
                $tablename = str_replace(' ','',$tablename);
            }

            foreach ($item as $item_num => $item_value)
            {
                if (mb_strstr($line,$item_value,FALSE) != FALSE)
                {
                    //Remove all " ` " in tablename, columnname etc
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

                    //ALL 'x'-int(n) to Integer
                    if($item_value == 'int(')
                    {
                        for($n = 0; $n <= 30; $n++){
                            $line = str_replace('small' . $item_value . $n . ')','Integer',$line);
                            $line = str_replace($item_value . $n . ')','Integer',$line);
                            $line = str_replace('\'','',$line);
                        }
                    }

                    //Remove all COMMENT, write comments in array divide column-comments and table-comments
                    if($item_value == 'COMMENT')
                    {
                        $cache_value = mb_strstr($line,$item_value,FALSE); //from ELEMENT to END
                        $cache_value_last = NULL;

                        if((mb_strstr($cache_value, ';', FALSE)) != FALSE){
                            $cache_value_last = mb_strstr($cache_value, ';', FALSE);
                        }elseif((mb_strstr($cache_value, ',', FALSE)) != FALSE){
                            $cache_value_last = mb_strstr($cache_value, ',', FALSE);
                        }

                        $line = mb_strstr($line,$cache_value,TRUE). $cache_value_last;

                        // comment like: COMMENT ON {COLUMN,TABLE} my_table.my_column IS 'Employee ID number';
                        if(mb_strstr($line,' ',TRUE))
                        {
                            $cache_comment = strchr($cache_value,'\'',FALSE);
                            $cache_comment = str_replace(',','',$cache_comment);

                            if(mb_strstr($cache_value,'=',FALSE) != FALSE){
                                $comment[] = 'COMMENT ON TABLE '.$tablename.' IS '.$cache_comment;
                            }else{
                                $comment[] = 'COMMENT ON COLUMN '.$tablename.'.'.mb_strstr($line,' ',TRUE).' IS '.$cache_comment.';';
                            }
                        }
                    }


                    //remove all KEY lines, Convert KEY to CREATE INDEX  and write in index ARRAY
                    if($item_value == 'KEY'){
                        if((mb_strstr($line, 'PRIMARY')) == FALSE){
                            if((mb_strstr($line, 'CONSTRAINT')) == FALSE)
                            {
                                //cuts name of column from line
                                $cache_value_columnname = mb_strstr($line,')',TRUE);
                                $cache_value_columnname = mb_strstr($cache_value_columnname,'(',FALSE);
                                $cache_value_columnname = str_replace('(','',$cache_value_columnname);

                                //cuts name of referenced table from line
                                $cache_value_tablename = mb_strstr($line,'(',TRUE);
                                $cache_value_tablename = str_replace($item_value,'',$cache_value_tablename);
                                $cache_value_tablename = str_replace('_'.$cache_value_columnname,'',$cache_value_tablename);
                                $cache_value_tablename = str_replace(' ','',$cache_value_tablename);

                                $line = NULL;
                                $index[] = 'CREATE INDEX'.' '.$cache_value_tablename.'_'.$cache_value_columnname.' ON '.$cache_value_tablename.' ('.$cache_value_columnname.');';
                            }
                        }
                    }

                    //Remove all CHARSET AND STANDARD
                    if($item_value == 'ENGINE'){
                        $line = ");";
                        $content[$line_num - 1] = str_replace(',', '',$content[$line_num - 1]);
                    }
                }
            }
            //write converted line and attach comment with old line-content
            //$content[$line_num] = $line.'   /* CONVERTED: '.trim($content[$line_num]).'*/'."\n";
            if($line != NULL){
                $content[$line_num] = $line."\n";
            }else{
                $content[$line_num - 1] = str_replace(',', '',$content[$line_num - 1]);
                unset($content[$line_num]);
            }

        }

        foreach($index as $index_num => $index_line){
            $content[] = $index_line."\n";
        }
        foreach($comment as $comment_num => $comment_line){
            $content[] = $comment_line."\n";
        }
        return $content;
    }

    //convert lines to standard
    //divide 'CREATE TABLE'
    function convertFile($sqlFilename)
    {

        $handle = fopen($sqlFilename,"rw");
        $cache = NULL;
        $index = 0;
        $content [$index][] = "//converted file:\n\n\n";

        do {
            if(mb_strstr($cache,'CREATE TABLE',FALSE) != FALSE) {
                do {
                    $content[$index][] = $cache;
                    $cache = fgetss($handle,100);
                }while(mb_strpos($cache,';')== FALSE);

                $content[$index][] = $cache . "\n";
                $content[$index] = $this->convertFile_create($content[$index]);

                $index ++;
            }
            $cache = fgetss($handle,100);
        }while($cache != FALSE);

        var_dump($content);
        return NULL;
    }
}

$convert = new Convert;
var_dump($argv[1]);
$convert->main_mysql2any($argv[1]);

