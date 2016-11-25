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
        $item = array('double', 'longblob', 'CHARACTER SET','longtext','`', 'COMMENT','int(', 'unsigned', 'AUTO_INCREMENT', 'ENGINE', 'KEY', ';', 'datetime',',\'', 'UNLOCK TABLES', 'enum(');
        return $item;
    }

    //convert INSERT INTO lines
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
        $before = array();      //for statements before 'CREATE INDEX' like
        $index = array();      //for creating the 'CREATE INDEX' statements
        $comment = array();     //for creating the 'COMMENT' statements
        $return = array();
        $tablename = NULL;
        $columnname = NULL;

        foreach ($content as $line_num => $line)
        {
            $line = trim($line);
            $columnname = NULL;

            //extract the tablename from CREATE TABLE
            if(mb_strstr($line,'CREATE TABLE',FALSE) != FALSE)
            {
                $tablename = mb_strstr($line,'`',FALSE);
                $tablename = mb_strstr($tablename,'(',TRUE);
                $tablename = str_replace('`','',$tablename);
                $tablename = str_replace(' ','',$tablename);
            }

            //extract A! columnname from statement. It can be a name for a new column,
            //it can be a name for a key or something
            //Necessary for write the columnname: "columnname" if a column is named like a SQL order like "default" oder "from"
            //a column can be named like a SQL order but it should not  ...
            //code not optimal
            if($line != NULL){
                if($line[0] == "`"){
                    $columnname = mb_strstr($line,'`',FALSE);
                    $columnname = mb_strstr($columnname," ",TRUE);
                    $columnname = str_replace('`','',$columnname);
                }
            }

            if(mb_strstr($line,'enum(',FALSE)!= FALSE){

                $first = trim($line);
                $first_content = mb_strstr($first,'(',FALSE);
                $first_content = mb_strstr($first_content,')',TRUE);
                $first = "\n".'CREATE TYPE '.$tablename.'_'.mb_strstr($first,'enum(',TRUE).'AS ENUM '.$first_content.");";

                $before[] = $first;
            }

            foreach ($item as $item_num => $item_value)
            {
                if (mb_strstr($line,$item_value,FALSE) != FALSE)
                {

                    //CONVERT 'CHARACTER SET' to check()
                    if($item_value == 'CHARACTER SET'){
                        $item_to = mb_strstr($line,$item_value,TRUE);
                        $item_from = mb_strstr($line,$item_value,FALSE);
                        $item_from = str_replace($item_value.' ','',$item_from);
                        $item_from = mb_strstr($item_from,' ',FALSE);

                        $line = $item_to.$item_from;
                    }

                    //Remove all " ` " in tablename, columnname etc
                    if($item_value == '`'){
                        $line = str_replace($item_value,'',$line);
                    }

                    // convert all unsigned in CHECK()
                    if($item_value == 'unsigned'){
                        $cache_value_columnname = mb_strstr($line,' ',TRUE);
                        $line = str_replace($item_value,'CHECK('.$cache_value_columnname.' >= 0)',$line);
                    }

                    // Remove al AUTO_INCREMENT
                    if($item_value == 'AUTO_INCREMENT'){
                        $line = str_replace($item_value,' ',$line);
                    }

                    //ALL enum to TYPE
                    if($item_value == 'enum('){
                        $line = mb_strstr($line,'enum(',TRUE) .$tablename.'_'.mb_strstr($line,'enum(',TRUE).',';
                    }

                    //ALL datetime in timestamp
                    if($item_value == 'datetime')
                    {
                        $line = str_replace($item_value,'timestamp',$line);
                    }

                    //CONVERT 'LONGBLOB' NOT CLEAR!!
                    //for test convert in 'text' (option 'BYTEA')
                    if($item_value == 'longblob'){

                        $line = str_replace($item_value,'TEXT',$line);
                        /*
                        print("\nel que lee esto es una estupidez");
                        echo 'ERROR: ';
                        var_dump($line);
                        echo 'Type '.$item_value." can not clearly converted \n";
                        echo "See code line 150\n";
                        echo "operation aborted!\n";
                        exit;
                        */
                    }

                    //REPLACE 'double' into 'text'
                    if($item_value == 'double'){
                        $line = str_replace($item_value,'double precision',$line);
                    }

                    //REPLACE 'longtext' into 'text'
                    if($item_value == 'longtext'){
                        $line = str_replace($item_value,'text',$line);
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
                                $index[] = 'CREATE INDEX'.' '.$tablename.'_'.str_replace(',','_',$cache_value_columnname).' ON '.$tablename.' ('.$cache_value_columnname.');';
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
            //because the NULL-lines are deleted
            if (array_key_exists($line_num,$content)){
                if(mb_strstr($content[$line_num],');',FALSE) != FALSE) {

                    $forward = TRUE;
                    $n = 0;

                    do{
                        if (array_key_exists($line_num - $n,$content)){
                            if($n >= 1){
                                $content[$line_num - $n] = str_replace(",\n","\n",$content[$line_num - $n]);
                                $forward = FALSE;
                            };
                        }
                        $n++;
                    }while($forward);
                }
            }
        }

        foreach($before as $before_num => $before_line){
            $return[] = $before_line."\n";
        }

        foreach($content as $content_num => $content_line){
            $return[] = $content_line;
        }

        foreach($index as $index_num => $index_line){
            $return[] = $index_line."\n";
        }
        foreach($comment as $comment_num => $comment_line){
            $return[] = $comment_line."\n";
        }

        return $return;
    }

    //convert lines to standard
    //divide 'CREATE TABLE' and 'insert into'
    function convertFile($sqlFilename)
    {

        $handle = fopen($sqlFilename,"r");
        $newfile = fopen("convert_".$sqlFilename,"w+");
        $cache = NULL;
        $content[] = NULL;

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
/*
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
*/
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

