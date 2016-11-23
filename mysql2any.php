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

    //check existing, access and write-permissions
    function checkFile($sqlFilename)
    {
        if (is_writable($sqlFilename)) {

            if (!$handle = fopen($sqlFilename, "r+")) {
                echo "Kann die Datei $sqlFilename nicht öffnen";
                return FALSE;
            }

            fclose($handle);

        } else {
            echo "Die Datei $sqlFilename ist nicht beschreibbar oder existent";
            return FALSE;
        }

        return $sqlFilename;
    }

    //List with all wrong items
    function wrongItems()
    {
        $item = array('`', 'COMMENT','int(', 'unsigned', 'AUTO_INCREMENT', 'ENGINE', 'KEY', ';', 'datetime',',\'', 'UNLOCK TABLES', 'enum(');
        return $item;
    }

    //convert CREATE TABLE lines
    function convert_insert($content){

        $item = $this->wrongItems();

        foreach ($content as $line_num => $line)
        {
            $line = trim($line);

            foreach ($item as $item_num => $item_value)
            {
                if (mb_strstr($line,$item_value,FALSE) != FALSE)
                {
                    //Remove all " ` " in tablename, columnname etc
                    if($item_value == '`'){
                        $line = str_replace('`','   ',$line);
                    }

                    //REMOVE all UNLOCK
                    if($item_value == 'UNLOCK TABLES'){
                        $line = NULL;
                    }

                    //convert string '\'' to e'\''
                    if($item_value == ',\''){
                        $line = str_replace($item_value,',e\'',$line);
                    }
                }
            }
            //write converted line and ?attach comment with old line-content?
            $content[$line_num] = $line."\n";
        }
        return $content;
    }

    //convert CREATE TABLE lines
    function convert_create($content){

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

                    //ALL datetime in timestamp
                    if($item_value == 'datetime')
                    {
                        $line = str_replace($item_value,'timestamp',$line);
                    }

                    //ALL 'x'-int(n) to Integer
                    if($item_value == 'int(')
                    {
                        for($n = 0; $n <= 30; $n++){
                            $line = str_replace('small' . $item_value . $n . ')','Integer',$line);
                            $line = str_replace('big' . $item_value . $n . ')','Integer',$line);
                            $line = str_replace('tiny' . $item_value . $n . ')','Integer',$line);
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
                                //KEY `IDX_CATALOG_PRODUCT_ENTITY_ENTITY_TYPE_ID` (`entity_type_id`),
                                $cache_value_columnname = mb_strstr($line,')',TRUE);
                                $cache_value_columnname = mb_strstr($cache_value_columnname,'(',FALSE);
                                $cache_value_columnname = str_replace('(','',$cache_value_columnname);

                                $line = NULL;
                                $index[] = 'CREATE INDEX'.' '.$tablename.'_'.$cache_value_columnname.' ON '.$tablename.' ('.$cache_value_columnname.');';
                            }
                        }
                    }

                    //Remove all CHARSET AND STANDARD
                    if($item_value == 'ENGINE'){
                        $line = ");";
                    }
                }
            }
            //write converted line and ?attach comment with old line-content?
            //remove ',' in the line before ");"
            //$content[$line_num] = $line.'   /* CONVERTED: '.trim($content[$line_num]).'*/'."\n";
            if($line != NULL){
                $content[$line_num] = $line."\n";
            }else{
                unset($content[$line_num]);
            }

            //!!!!!Notice: Undefined offset: !!!!!
            //can be happen when $content[ line_num - n] do not exist
            //because the NULL-lines where deleted
            if (array_key_exists($line_num,$content)){
                if(mb_strstr($content[$line_num],');',FALSE) != FALSE) {

                    $forward = TRUE;
                    $n = 0;

                    do{
                        if (array_key_exists($line_num - $n,$content)){
                            if($n >= 1){
                                $content[$line_num - $n] = str_replace(',', '',$content[$line_num - $n]);
                                $forward = FALSE;
                            };
                        }
                        $n++;
                    }while($forward);
                }
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
    //divide 'CREATE TABLE' and 'insert into'
    function convertFile($sqlFilename)
    {

        $handle = fopen($sqlFilename,"r");
        $newfile = fopen("convert_".$sqlFilename,"w+");
        $cache = NULL;
        $content[] = "/*converted file:".$sqlFilename."\n\n\n */";

        do {

            //Convertiert create table
            if(mb_strstr($cache,'CREATE TABLE',FALSE) != FALSE){
                do {
                    $content[] = $cache;
                    $cache = NULL; // STACK
                    $cache = fgets($handle);

                }while(mb_strpos($cache,';')== FALSE);

                $content[] = $cache . "\n";
                $content = $this->convert_create($content);

                foreach($content As $con_num => $con_line){
                    fwrite($newfile,$con_line);
                }

                fwrite($newfile,"\n");
                $content = NULL; // STACK
            }

            if(mb_strstr($cache,'insert',FALSE) != FALSE OR mb_strstr($cache,'INSERT',FALSE) != FALSE){
                do {
                    $content[] = $cache;
                    $cache = NULL; // STACK
                    $cache = fgets($handle);

                }while(mb_strpos($cache,';')== FALSE);

                $content[] = $cache . "\n";
                $content  = $this->convert_insert($content);

                foreach($content As $con_num => $con_line){
                    fwrite($newfile,$con_line);
                }

                fwrite($newfile,"\n");
                $content = NULL; // STACK
            }

            $cache = fgets($handle);
        }while($cache != FALSE);

        fclose($handle);
        fclose($newfile);

        return NULL;
    }
}

$convert = new Convert;
var_dump($argv[1]);
$convert->main_mysql2any($argv[1]);

