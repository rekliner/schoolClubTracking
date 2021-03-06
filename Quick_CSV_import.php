<?php
    class Quick_CSV_import
    {
      var $table_name; //where to import to
      var $file_name;  //where to import from
      var $use_csv_header; //use first line of file OR generated columns names
      var $field_separate_char; //character to separate fields
      var $field_enclose_char; //character to enclose fields, which contain separator char into content
      var $field_escape_char;  //char to escape special symbols
      var $error; //error message
	  var $line_termination_char; //chars to end lines
      var $arr_csv_columns; //array of columns
      var $table_exists; //flag: does table for import exist
      var $table_wipe; //flag: erase table before inserting
      var $encoding; //encoding table, used to parse the incoming file. Added in 1.5 version

      function Quick_CSV_import($file_name="")
      {
        $this->file_name = $file_name;
        $this->arr_csv_columns = array();
        $this->use_csv_header = true;
        $this->field_separate_char = ",";
        $this->field_enclose_char  = "\"";
        $this->field_escape_char   = "\\";
        $this->line_termination_char   = "\r\n";
		$this->table_wipe = false;
		$this->table_drop = false;
        $this->table_exists = false;
		$this->encoding = "default";
      }

      function import()
      {
        if($this->table_name=="")
          $this->table_name = "temp_".date("d_m_Y_H_i_s");
	  
		if ($this->table_drop == true) {
			$sql = "DROP TABLE " . $this->table_name ;
			$res = @mysql_query($sql);
			$this->error = mysql_error();			
			$this->table_exists = false;
		}
        if ($this->table_exists == false)
        $this->create_import_table();

        if(empty($this->arr_csv_columns))
          $this->get_csv_header_fields();

        /* change start. Added in 1.5 version */
        if("" != $this->encoding && "default" != $this->encoding)
          $this->set_encoding();
        /* change end */

        if($this->table_exists)
        {
		  if($this->table_wipe)
		  {
			$sql = "TRUNCATE " . $this->table_name;
			$res = @mysql_query($sql);
			$this->error = mysql_error();
		  }
          $sql = "LOAD DATA LOCAL INFILE '".@mysql_escape_string($this->file_name).
                 "' INTO TABLE `".$this->table_name.
                 "` FIELDS TERMINATED BY '".@mysql_escape_string($this->field_separate_char).
                 "' OPTIONALLY ENCLOSED BY '".@mysql_escape_string($this->field_enclose_char).
                 "' ESCAPED BY '".@mysql_escape_string($this->field_escape_char).
                 "' LINES TERMINATED BY '".@mysql_escape_string($this->line_termination_char).
                 "' ".
                 ($this->use_csv_header ? " IGNORE 1 LINES " : "")
                 ."(`".implode("`,`", $this->arr_csv_columns)."`)";
          $res = @mysql_query($sql);
          $this->error += mysql_error();
		  return "Imported entries successfully.";
        }
      }

      //returns array of CSV file columns
      function get_csv_header_fields()
      {
        $this->arr_csv_columns = array();
        $fpointer = fopen($this->file_name, "r");
        if ($fpointer)
        {
          $arr = fgetcsv($fpointer, 10*1024, $this->field_separate_char);
          if(is_array($arr) && !empty($arr))
          {
            if($this->use_csv_header)
            {
              foreach($arr as $val)
                if(trim($val)!="")
                  $this->arr_csv_columns[] = $val;
            }
            else
            {
              $i = 1;
              foreach($arr as $val)
                if(trim($val)!="")
                  $this->arr_csv_columns[] = "column".$i++;
            }
          }
          unset($arr);
          fclose($fpointer);
        }
        else
          $this->error = "file cannot be opened: ".(""==$this->file_name ? "[empty]" : @mysql_escape_string($this->file_name));
        return $this->arr_csv_columns;
      }

      function create_import_table()
      {
        $sql = "CREATE TABLE IF NOT EXISTS ".$this->table_name." (";

        if(empty($this->arr_csv_columns))
          $this->get_csv_header_fields();

        if(!empty($this->arr_csv_columns))
        {
          $arr = array();
          for($i=0; $i<sizeof($this->arr_csv_columns); $i++)
              $arr[] = "`".$this->arr_csv_columns[$i]."` TEXT";
          $sql .= implode(",", $arr);
          $sql .= ")";
          $res = @mysql_query($sql);
          $this->error = "104" . mysql_error();
          $this->table_exists = ""==mysql_error();
        }
      }

      /* change start. Added in 1.5 version */
      //returns recordset with all encoding tables names, supported by your database
      function get_encodings()
      {
        $rez = array();
        $sql = "SHOW CHARACTER SET";
        $res = @mysql_query($sql);
        if(mysql_num_rows($res) > 0)
        {
          while ($row = mysql_fetch_assoc ($res))
          {
            $rez[$row["Charset"]] = ("" != $row["Description"] ? $row["Description"] : $row["Charset"]); //some MySQL databases return empty Description field
          }
        }
        return $rez;
      }

      //defines the encoding of the server to parse to file
      function set_encoding($encoding="")
      {
        if("" == $encoding)
          $encoding = $this->encoding;
        $sql = "SET SESSION character_set_database = " . $encoding; //'character_set_database' MySQL server variable is [also] to parse file with rigth encoding
        $res = @mysql_query($sql);
        return "133" . mysql_error();
      }
      /* change end */

    }

    ?>