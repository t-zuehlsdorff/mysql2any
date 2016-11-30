<?php
/**
 * Created by PhpStorm.
 * User: c.basten
 * Date: 17.11.2016
 * Time: 12:58
 */


    function main_mysql2any($sqlFilename)
    {
        $sqlFilename = checkFile($sqlFilename);

        if ($sqlFilename != NULL){
            convertFile($sqlFilename);
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
    //obsolet
    function wrongItems()
    {
        $item = array('double', 'longblob', 'CHARACTER SET','longtext','`', 'COMMENT','int(', 'unsigned', 'AUTO_INCREMENT', 'ENGINE', 'KEY', ';', 'datetime',',\'', 'UNLOCK TABLES', 'enum(');
        return $item;
    }

    //convert INSERT INTO lines
    function convert_insert($content){

        $item =  wrongItems();

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

    //extract A! columnname from statement
    function give_columnname($line)
    {
        if(mb_strstr($line,'"',FALSE)!= FALSE)
        {
            $columnname = mb_strstr($line,'"',FALSE);
            $columnname = mb_strstr($columnname," ",TRUE);
            $columnname = str_replace('"','',$columnname);

        } else{$columnname = NULL;};

        return $columnname;
    }

    //extract the tablename from CREATE TABLE
    //if no new Tablename, return old
    function give_tablename($line, $tablename)
    {
        if(mb_strstr($line,'CREATE TABLE',FALSE) != FALSE)
        {
            $tablename = mb_strstr($line,'"',FALSE);
            $tablename = mb_strstr($tablename,'(',TRUE);
            $tablename = str_replace('"','',$tablename);
            $tablename = str_replace(' ','',$tablename);
        }
        return $tablename;
    }

    //convert all '`' to '"'
    function convert_quote($line)
    {
        $line = str_replace('`','"',$line);
        return $line;
    }

    //creates ENUM() expression. Give NULL if no ENUM
    function create_enum($line,$tablename)
    {
        if(mb_strstr($line,'enum(',FALSE)!= FALSE)
        {
            $line = trim($line);
            $line = str_replace('"','',$line);
            $content = mb_strstr($line,'(',FALSE);
            $content = mb_strstr($content,')',TRUE);
            $line = 'CREATE TYPE "'.$tablename.'_'. mb_strstr($line,' enum(',TRUE).'" AS ENUM '.$content.');';

        }else{$line = NULL;};
        return $line;
    }

    //CONVERT 'CHARACTER SET' to check() give converted line
    function convert_character($line)
    {
        if(mb_strstr($line,'CHARACTER SET',TRUE) != FALSE){
            $item_to = mb_strstr($line,'CHARACTER SET',TRUE);
            $item_from = mb_strstr($line,'CHARACTER SET',FALSE);
            $line = str_replace('CHARACTER SET'.' ','',$line);

        }
        return $line;
    }

    // convert data-types
    //include converting enum()
    function convert_datatypes($line,$tablename)
    {
        //CONVERT via reg_replace
        $regArray = array('/[^ ]+nt\((\d+)\)/' => 'Integer', '/enum[^ ]+/' => $tablename.'_'.mb_strstr($line,'" enum(',TRUE), '/\_\"/' => '_',
                        '/longblob/' => 'TEXT', '/double/' => 'double precision', '/longtext/' => 'text', '/datetime/' => 'timestamp', '/AUTO_INCREMENT/' => '',
                        '/unsigned/' => 'CHECK('.mb_strstr($line,' ',TRUE).' >= 0)', '/\`/' => '"', '/\) ENGINE.+/' => ');');
        foreach($regArray AS $from => $to) {
            $line = preg_replace($from, $to, $line);
        }

            return $line;
    }

    // create Indixies
    // Schema: CREATE INDEX "name" ON "tablename"("columnname");
    //remove all KEY lines, Convert KEY to CREATE INDEX  and write in index ARRAY
    function create_index($line, $tablename, $convert)
    {
        $new = NULL;
        $preVar = NULL;
        $new = NULL;

        if((mb_strstr($line, 'KEY')) != FALSE) {
            if ((mb_strstr($line, 'PRIMARY')) == FALSE) {
                if ((mb_strstr($line, 'CONSTRAINT')) == FALSE) {
                    if ($convert == TRUE) {

                        $preVar = ' ';
                        $new = $line;

                        if ((mb_strstr($line, 'UNIQUE')) != FALSE) {
                            $preVar = ' UNIQUE ';
                        }

                        $regArray = array('/.*KEY/' => ('CREATE'.$preVar.'INDEX'), '/\`/' => '"', '/\s\"/' => (' "' . $tablename . '_'),'/\).*/' => ');');
                        foreach ($regArray AS $from => $to) {
                            $new = preg_replace($from, $to, $new);
                        }
                    }else{
                        $line = NULL;
                    }
                }
            }
        }

        if($convert == TRUE){
            return $new;
        }else{
            return $line;
        }
    }

    //Remove all COMMENT, write comments in array divide column-comments and table-comments
    function convert_comment($line,$tablename){

        if(mb_strstr($line,'COMMENT',FALSE) != FALSE)
        {
            $cache_value = mb_strstr($line,'COMMENT',FALSE); //from ELEMENT to END
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
        return  NULL;
    }

    function format_content($content,$line_num){

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

        return $content;
    }

    //convert CREATE TABLE lines
    function convert_create($content){

        $item = wrongItems();
        $before = array();      //for statements before 'CREATE INDEX' like
        $index = array();      //for creating the 'CREATE INDEX' statements
        $comment = array();     //for creating the 'COMMENT' statements
        $return = array();
        $tablename = NULL;

        foreach ($content as $line_num => $line)
        {
            $line = trim($line);
            $line = convert_quote($line);

            $tablename = give_tablename($line,$tablename);

            $before[] = create_enum($line,$tablename);
            $line = convert_datatypes($line,$tablename);
            $line = convert_character($line);
            $index[] = create_index($line,$tablename,TRUE); //write new INDEX
            $line = create_index($line,$tablename,FALSE);   //delete old KEY from line
            $comment[] = convert_comment($line,$tablename);

            $content = format_content($content,$line_num);

            //write converted line and ?attach comment with old line-content?
            //remove ',' in the line before ");"
            //$content[$line_num] = $line.'   /* CONVERTED: '.trim($content[$line_num]).'*/'."\n";
            if($line != NULL){
                $content[$line_num] = $line."\n";
            }else{
                unset($content[$line_num]);
            }


        }

        foreach($before as $before_num => $before_line){
            if($before_line != NULL){
                $return[] = $before_line."\n";
            }
        }

        foreach($content as $content_num => $content_line){
            $return[] = $content_line;
        }
        foreach($index as $index_num => $index_line){
            if($index_line != NULL){
                $return[] = $index_line."\n";
            }
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
                $content = convert_create($content);

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
                $content  = convert_insert($content);

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

var_dump($argv[1]);
main_mysql2any($argv[1]);

