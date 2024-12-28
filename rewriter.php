<?php

# index, hash and rewrite files

# change log
# 2024-12-26 21:07

if (!extension_loaded('sqlite3')) {
  echo "SQLite3 extension not loaded\n";
  exit(1);
}

define('FILENAME_DB', '.rewriter.sqlite3');
define('REWRITE_TIME_LIMIT', 86400 * 365 * 4);

# status codes
define('STATUS_ERROR_REWRITE_EXEC_CP_FAILED', -5);
define('STATUS_ERROR_REWRITE_EXEC_MV_FAILED', -6);
define('STATUS_ERROR_REWRITE_COPY_MISSING', -1);
define('STATUS_ERROR_REWRITE_MD5_FAILED', -2);
define('STATUS_ERROR_REWRITE_MD5_MISMATCH', -3);
define('STATUS_ERROR_REWRITE_META_MISMATCH', -4);
define('STATUS_ERROR_REWRITE_SET_CHGRP_FAILED', -7);
define('STATUS_ERROR_REWRITE_SET_CHMOD_FAILED', -8);
define('STATUS_ERROR_REWRITE_SET_CHOWN_FAILED', -9);
define('STATUS_ERROR_REWRITE_SIZE_CHECK_FAILED', -10);
define('STATUS_ERROR_REWRITE_DISK_FREE_SPACE_CHECK_FAILED', -11);
define('STATUS_ERROR_REWRITE_FILE_LARGER_THAN_DISK_FREE_SPACE', -12);
define('STATUS_ERROR_MISSING', -13);
define('STATUS_ERROR_MD5_MISMATCH', -14);
define('STATUS_NONE', 0);
define('STATUS_UNVERIFIED', 1);
define('STATUS_VERIFIED', 2);
define('STATUS_REWRITTEN', 3);

$config_dbpath = false;

$opts = getopt('cd:hi:o:r:');

function get_db_conn() {
  global $config_dbpath;
  $cwd = getcwd();
  if (!$config_dbpath) {
  $config_dbpath = locate_db($cwd);
  }
  return new SQLite3($config_dbpath);
}

# find database file, only 1 allowed
function locate_db($dbpath) {
  echo "Locating database... ";
  $path = realpath($dbpath);
  $parts = explode("/", trim($path, "/"));
  $candidates = array();
  $pop = true;
  while ($pop) {
    $pathpart = '/'.implode("/", $parts);
    if (count($parts) > 0) $pathpart .= '/';
    $path = $pathpart.FILENAME_DB;
    if (file_exists($path)) {
      $candidates[] = $path;
    }
    if (!count($parts)) $pop = false;
    array_pop($parts);
  }

  if (count($candidates) < 1) {
    echo "no database found in ".$dbpath."\n";
    exit(1);
  } else if (count($candidates) > 1) {
    echo "multiple found in ".$dbpath.":\n";
    foreach ($candidates as $c) {
      echo '- '.$c."\n";
    }
    echo "Please use -d<path> to specify which one to use.\n";
    exit(1);
  }
  echo $candidates[0]."\n";
  return $candidates[0];
}

# escape string
function mres($s) {
  return SQLite3::escapeString($s);
}

# get number of rows in sqlite result
function sqlite3_num_rows($r) {
  if ($r === false) return 0;
  $num = 0;
  while($row = $r->fetchArray()) {
    ++$num;
  }
  return $num;
}

# check options
foreach ($opts as $k => $v) {
  switch ($k) {
    case 'c': # create
      if (file_exists(FILENAME_DB)) {
        echo 'File already exists: '.FILENAME_DB."\n";
        break;
      }
      $db = new SQLite3(FILENAME_DB);
      if (!$db || !file_exists(FILENAME_DB)) {
        echo 'Failed creating '.FILENAME_DB."\n";
        exit(1);
      }

      if (!$db->query(
        'CREATE TABLE files (
          id INTEGER PRIMARY KEY,
          name TEXT NOT NULL,
          md5_hash TEXT,
          md5_hash_created TEXT,
          md5_created TEXT,
          md5_updated TEXT,
          rewritten TEXT,
          status INTEGER DEFAULT 0
        )')) exit(1);

      echo "Created ".realpath(FILENAME_DB)."\n";
      break;
    case 'd': # set database file
      $f = realpath($v);
      if (!file_exists($f)) {
        echo "Database file not found: $v\n";
        exit(1);
      }
      $config_dbpath = $f;
      echo "Database file: ".$f."\n";
      break;
    case 'h':
?>Usage: <?php echo basename($argv[0]); ?> <parameters>
Parameters:
  -c        Create <?php echo FILENAME_DB ?> SQLite3 file in current directory
  -d<path>  Use other database file, defaults to <?php echo FILENAME_DB ?> in current directory
  -h        Print this help
  -i<path>  Import MD5 file to database from path
  -o<path>  Output MD5 file with files in current directory found in database to path
  -r<option>
    option:
      i   Index files in current directory to database
      h   Hash files in current directory found in database
      v   Validate MD5 hashes on files in current directory
      w   Rewrite files in current directory found in database
<?php
      break;
    case 'i': # import md5, parts are taken from md5filecheck

      $db = get_db_conn();
      $cwd = getcwd();
      # get path difference, db vs cwd
      $dbpath = trim(dirname($config_dbpath), "/");
      $cwd = trim($cwd, "/");
      # dbpath: aaa/bbb
      # cwd   : aaa/bbb/ccc
      if (strpos($cwd, $dbpath) !== 0) {
        echo "Cannot find database path in current directory path";
        exit(1);
      }
      $dbparts = explode("/", $dbpath);
      $cwdparts = explode("/", $cwd);
      $diffparts = $cwdparts;
      array_splice($diffparts, 0, count($dbparts));
      $pathdiff = implode("/", $diffparts);
      if (strlen($pathdiff)) $pathdiff .= '/';

      $fileerror = false;

      # get header
      function getheader($i, $linecount, $stats, $text) {
        return '['.
          str_pad($i, strlen($linecount), ' ', STR_PAD_LEFT).'/'.$linecount.' '.
          str_pad(round($i / $linecount *  100), 3, ' ', STR_PAD_LEFT).'% '.
          str_pad($stats['inserted'], strlen($linecount), ' ', STR_PAD_LEFT).' inserted '.
          str_pad($stats['updated'], strlen($linecount), ' ', STR_PAD_LEFT).' updated '.
          str_pad($stats['missing'], strlen($linecount), ' ', STR_PAD_LEFT).' missing'.
          '] '.$text;
      }

      $errorlog = false;
      $file = $v;
      $fileerror = false;

      if (!file_exists($file)) {
        echo 'File not found: '.$file."\n";
        die(1);
      }

      $modified = filemtime($file);
      if ($modified === false) {
        echo 'Failed reading modify date: '.$modified."\n";
        exit(1);
      }
      $modified = date('Y-m-d H:i:s', $modified);

      # count lines
      $linecount = 0;
      $f = fopen($file, "r");
      if (!$f) {
        echo 'Failed opening: '.$file."\n";
        die(1);
      }

      # calculate lines
      while(!feof($f)){
        $line = fgets($f, 4096);
        $linecount = $linecount + substr_count($line, PHP_EOL);
      }

      if (!rewind($f)) {
        echo 'Failed rewinding '.$file."\n";
        fclose($f);
        die(1);
      }

      $i=0;
      $stats = array(
        'missing' => 0,
        'inserted' => 0,
        'updated' => 0,
      );

      $header = '';
      # loop files
      while ($line = fgets($f)) {

        $i++;
        $line = $line;
        preg_match('/([a-zA-Z0-9]+)  (.*)\r?\n?/', $line, $matches);

        if (!isset($matches[1], $matches[2])) {
          echo 'Failed splitting line '.$i."\n";
          continue;
        }

        $md5 = $matches[1];
        $fullpath = realpath($matches[2]);

        $filesize = filesize($fullpath);
        echo str_repeat(' ', strlen($header))."\r";
        $header = getheader($i, $linecount, $stats, 'Importing '.$fullpath.' '.$filesize.' b');
        echo $header."\r";

        if (!file_exists($fullpath)) {
          $currentstatus = 'MISSING';
          $stats['missing']++;
        } else {
          $currentstatus = 'OK';
          $basename = basename($fullpath);
          $dirname = trim(dirname($fullpath), "/");
          $dirparts = explode("/", $dirname);
          array_splice($dirparts, 0, count($dbparts));
          $pathdiff = implode("/", $dirparts);
          if (strlen($pathdiff)) $pathdiff .= "/";
          $relativepath = $pathdiff.$basename;


          $r = $db->query('SELECT * FROM files WHERE name="'.mres($relativepath).'"');
          if (!sqlite3_num_rows($r)) {
            if (!$db->query('INSERT INTO files (name, md5_hash, md5_created, status) VALUES("'.mres($relativepath).'", "'.mres($md5).'", "'.mres($modified).'", "'.mres(STATUS_UNVERIFIED).'")')) exit(1);
            $stats['inserted']++;
            $currentstatus = 'ADD';
          } else {
            while ($row = $r->fetchArray()) {
              if ($row['md5_hash'] == null || !strlen($row['md5_hash'])) {
                # echo "Setting hash for $relativepath\n";
                if (!$db->query('UPDATE files SET md5_hash="'.mres($md5).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                $stats['updated']++;
                $currentstatus = 'UPDATE';
              }
              break; # only first
            }
          }
        }
        # clear previous line
        echo str_repeat(' ', strlen($header))."\r";
        # print result
        $header = getheader($i, $linecount, $stats, $currentstatus.' '.$fullpath."\r");
        echo $header;
      }
      echo str_repeat(' ', strlen($header))."\r";
      # print result
      echo getheader($i, $linecount, $stats, "\r")."\n";
      fclose($f);

      break;
    case 'o': # output md5
      $db = get_db_conn();

      $r = $db->query("SELECT * FROM files ORDER BY name");

      echo "Writing to $v\n";
      $f = fopen($v, 'w');

      $i=0;
      $l = "";
      $ftotal = sqlite3_num_rows($r);
      $fwritten = 0;
      $fmissingmd5 = 0;
      while($row = $r->fetchArray()) {
        $md5 = $row['md5_hash'];
        if (!strlen($row['md5_hash'])) {
          $md5 = 0;
          $fmissingmd5++;
        }
        if (!fputs($f, $l.$md5.'  '.$row['name'])) {
          echo "Failed writing $v\n";
          exit(1);
        }
        if (!$i) {
          $l = "\n";
        }
        $i++;
        $fwritten++;
      }
      fclose($f);
      echo "$ftotal files found, $fwritten written to file, $fmissingmd5 without MD5 hashes\n";
      if ($fmissingmd5) {
          echo "Warning! $fmissingmd5 files has no MD5 hashes, used 0 as MD5 for those\n";
      }
      break;
    case 'r': # re-something
      switch ($v) {
        case 'i': # index
          $db = get_db_conn();
          $cwd = getcwd();

          echo "Indexing files in ".$cwd."\n";

          $dbpath = trim(dirname($config_dbpath), "/");
          $cwd = trim($cwd, "/");
          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $dbpath) !== 0) {
            echo "Cannot find database path in current directory path";
            exit(1);
          }
          $dbparts = explode("/", $dbpath);
          $cwdparts = explode("/", $cwd);
          $diffparts = $cwdparts;
          array_splice($diffparts, 0, count($dbparts));
          $pathdiff = implode("/", $diffparts);
          if (strlen($pathdiff)) $pathdiff .= '/';

          $c = 'find . -type f ! -name \''.FILENAME_DB.'\'|sort';
          exec($c, $o, $r);
          if ($r !== 0) {
            echo 'Failed: '.$c.": \n".implode("\n", $o)."\n";
            exit(1);
          }

          $ftotal = count($o);
          $fadded = 0;
          foreach ($o as $k1 => $v1) {
            $v1 = $pathdiff.ltrim($v1, './');
            $r = $db->query('SELECT * FROM files WHERE name="'.SQLite3::escapeString($v1).'"');
            if (!sqlite3_num_rows($r)) {
              echo 'Adding '.$v1."\n";
              if (!$db->query('INSERT INTO files (name, status) VALUES("'.SQLite3::escapeString($v1).'", "'.mres(STATUS_UNVERIFIED).'")')) exit(1);
              $fadded++;
            }
          }
          echo "$ftotal files found, added $fadded\n";
          break;
        case 'h': # hash
          $db = get_db_conn();
          $cwd = getcwd();
          echo "Hashing files in ".$cwd."\n";

          $dbpath = trim(dirname($config_dbpath), "/");
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $dbpath) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $dbparts = explode("/", $dbpath);
          $cwdparts = explode("/", $cwd);
          $diffparts = $cwdparts;
          array_splice($diffparts, 0, count($dbparts));
          $pathdiff = implode("/", $diffparts);
          if (strlen($pathdiff)) $pathdiff .= '/';

          # this gives file paths relative to dbpath
          $sql = 'SELECT * FROM files WHERE name LIKE "'.SQLite3::escapeString($pathdiff).'%" AND (md5_hash IS NULL OR md5_hash = "")';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No indexed unhashed files in $cwd\n";
            exit(1);
          }

          echo sqlite3_num_rows($r)." unhashed files\n";

          $cwd = trim($cwd, "/");
          $cutoff = count($diffparts);
          $numrehashed = 0;
          while($row = $r->fetchArray()) {
            $dbfilename = basename($row['name']);
            $relativepath = $dbfilename;

            if (strpos($row['name'], '/') !== false) {

              $dbfilepath = dirname($row['name']);
              $dbfilepath = trim($dbfilepath, "/");
              $dbparts = explode("/", $dbfilepath);

              if ($cutoff > 0) {
                array_splice($diffparts, 0, $cutoff);
              }
              $relativepath = implode("/", $dbparts);
              if (strlen($relativepath)) $relativepath .= '/';
              $relativepath = $relativepath.$dbfilename;
            }

            echo "Hashing $relativepath\n";

            if (!file_exists($relativepath)) {
              echo " - file not found\n";
              continue;
            }

            $md5 = md5_file($relativepath);
            if ($md5 !== false) {
              if (!$db->query('
                UPDATE files
                SET
                  md5_hash="'.mres($md5).'",
                   md5_created = "'.mres(date("Y-m-d H:i:s")).'",
                  status="'.mres(STATUS_VERIFIED).'"
                WHERE id="'.mres($row['id']).'"')) exit(1);
              $numrehashed++;
            }
          }
          echo "Hashed $numrehashed files\n";
          break;
        case 'v':

          # --- begin

          $fileerror = false;

          # open a log file if not already open, then append text to it
          function logtext(&$filepointer, $file, $text) {
              # file not already opened
              if (!$filepointer) {
                  # file not set get out
                  if (!$file) return;
                  # try to open file, put pointer to the beginning of it
                  $filepointer = fopen($file, 'w+');
                  if (!$filepointer) {
                      echo 'Failed opening log file '.$file."\n";
                      die(1);
                  }
              }
              # write the text to the file
              fputs($filepointer, $text);
          }

          # get header
          function getheader($i, $linecount, $stats, $text) {
            return '['.
              str_pad($i, strlen($linecount), ' ', STR_PAD_LEFT).'/'.$linecount.' '.
              str_pad(round($i / $linecount *  100), 3, ' ', STR_PAD_LEFT).'% '.
              str_pad($stats['ok'], strlen($linecount), ' ', STR_PAD_LEFT).' OK '.
              str_pad($stats['unhashed'], strlen($linecount), ' ', STR_PAD_LEFT).' unhashed '.
              str_pad($stats['mismatch'], strlen($linecount), ' ', STR_PAD_LEFT).' mismatch '.
              str_pad($stats['missing'], strlen($linecount), ' ', STR_PAD_LEFT).' missing'.
              '] '.$text;
          }

          $db = get_db_conn();
          $cwd = getcwd();
          echo "Verifying files in ".$cwd."\n";

          $dbpath = trim(dirname($config_dbpath), "/");
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $dbpath) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $dbparts = explode("/", $dbpath);
          $cwdparts = explode("/", $cwd);
          $diffparts = $cwdparts;
          array_splice($diffparts, 0, count($dbparts));
          $pathdiff = implode("/", $diffparts);
          if (strlen($pathdiff)) $pathdiff .= '/';

          # this gives file paths relative to dbpath

          $sql = 'UPDATE files SET status="'.mres(STATUS_UNVERIFIED).'" WHERE name LIKE "'.SQLite3::escapeString($pathdiff).'%"';
          if (!$db->query($sql)) exit(1);

          $sql = 'SELECT * FROM files WHERE name LIKE "'.SQLite3::escapeString($pathdiff).'%"';
          $r = $db->query($sql);
          $total = sqlite3_num_rows($r);
          if (!$total) {
            echo "No files found in $cwd\n";
            exit(1);
          }

          echo $total." unverified files\n";

          $errorlog = false;

          $fileerror = false;

          #foreach (getopt('e:hi:', array('errorlog:', 'help', 'input:')) as $opt => $value) {
          #    switch ($opt) {
          #        case 'e':
          #        case 'errorlog':
          #            $errorlog = $value;
          #            break;

          #    -h, --help
          #        Print this help
          #    -i/--input <filename>
          #        Read checksums from this file (required).



          # count lines
          $linecount = $total;

          $i=0;
          $stats = array(
            'mismatch' => 0,
            'missing' => 0,
            'ok' => 0,
            'unhashed' => 0
          );

          $cwd = trim($cwd, "/");
          $cutoff = count($diffparts);
          $header = '';
          # loop files
          while ($row = $r->fetchArray()) {

            $i++;

            $md5 = $row['md5_hash'];

            # --   ---
            $dbfilename = basename($row['name']);
            $relativepath = $dbfilename;

            if (strpos($row['name'], '/') !== false) {

              $dbfilepath = dirname($row['name']);
              $dbfilepath = trim($dbfilepath, "/");
              $dbparts = explode("/", $dbfilepath);

              if ($cutoff > 0) {
                array_splice($diffparts, 0, $cutoff);
              }
              $relativepath = implode("/", $dbparts);
              if (strlen($relativepath)) $relativepath .= '/';
              $relativepath = $relativepath.$dbfilename;
            }
            $path = $relativepath;
            # --   ---

            $filesize = filesize($path);
            echo str_repeat(' ', strlen($header))."\r";
            $header = getheader($i, $linecount, $stats, 'MD5-summing '.$path.' '.$filesize.' b');
            echo $header."\r";

            if (!file_exists($path)) {
              $currentstatus = 'MISSING';
              $stats['missing']++;
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_MISSING).'" WHERE id="'.mres($row['id']).'"')) exit(1);
            } else if ($md5 == null || !strlen($md5)) {
              $currentstatus = 'UNHASHED';
              $stats['unhashed']++;
            } else {
              if (md5_file($path) === $md5) {
                $stats['ok'] ++;
                $currentstatus = 'OK';
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_VERIFIED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              } else {
                $stats['mismatch'] ++;
                $currentstatus = 'MISMATCH';
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_MD5_MISMATCH).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              }
            }
            # clear previous line
            echo str_repeat(' ', strlen($header))."\r";
            # print result
            $header = getheader($i, $linecount, $stats, $currentstatus.' '.$path."\r");

            if ($currentstatus !== 'OK' && $errorlog !== false) {
              logtext($fileerror, $errorlog, $header);
            }
            echo $header;
          }
          echo str_repeat(' ', strlen($header))."\r";
          # print result
          echo getheader($i, $linecount, $stats, "\r")."\n";

          if ($fileerror) fclose($fileerror);


          # --- end

          break;
        case 'w': # rewrite
          $db = get_db_conn();
          $cwd = getcwd();
          echo "Rewriting files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          $dbpath = trim(dirname($config_dbpath), "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $dbpath) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $dbparts = explode("/", $dbpath);
          $cwdparts = explode("/", $cwd);
          $diffparts = $cwdparts;
          array_splice($diffparts, 0, count($dbparts));
          $pathdiff = implode("/", $diffparts);
          if (strlen($pathdiff)) $pathdiff .= '/';

          # this gives file paths relative to dbpath
          $sql = 'SELECT * FROM files WHERE name LIKE "'.SQLite3::escapeString($pathdiff).'%"';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No files found in $cwd\n";
            exit(1);
          }

          echo sqlite3_num_rows($r)." files in db and directory\n";

          $cwd = trim($cwd, "/");
          $cutoff = count($diffparts);
          $failed = 0;
          $notfound = 0;
          $rewritten = 0;

          while($row = $r->fetchArray()) {

            if ($row['rewritten'] != null && strlen($row['rewritten']) > 0 &&
              strtotime($row['rewritten']) > (time() - REWRITE_TIME_LIMIT)) {
              continue;
            }

            $dbfilename = basename($row['name']);
            $relativepath = '';

            if (strpos($row['name'], '/') !== false) {

              $dbfilepath = dirname($row['name']);
              $dbfilepath = trim($dbfilepath, "/");
              $dbparts = explode("/", $dbfilepath);

              if ($cutoff > 0) {
                array_splice($diffparts, 0, $cutoff);
              }
              $relativepath = implode("/", $dbparts);
              if (strlen($relativepath)) $relativepath .= '/';

            }

            $tmpfile = $relativepath.'.rewrite-tmp';
            $srcfile = $relativepath.$dbfilename;

            if (!file_exists($srcfile)) {
              echo " - source file not found\n";
              $notfound++;
              continue;
            }

            # get file size
            $size = filesize($srcfile);
            if ($size === false) {
              echo '- size check failed'."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SIZE_CHECK_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              continue;
            }

            # get free space
            $freespace = disk_free_space($relativepath);
            if ($freespace === false) {
              echo '- disk free space check failed'."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_DISK_FREE_SPACE_CHECK_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              exit(1); # fatal
            }

            # check file size vs free space
            if ($size > $freespace) {
              echo '- not enough disk free space to cp, '.$freespace.' b free, need '.$size.' b'."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_FILE_LARGER_THAN_DISK_FREE_SPACE).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              continue;
            }

            echo "Copying $srcfile to $tmpfile\n";

            $c = 'cp '.escapeshellarg($srcfile).' '.escapeshellarg($tmpfile);
            echo 'Run: '.$c."\n";
            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo '- cp command failed: '.implode(" ", $o)."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_EXEC_CP_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              if (file_exists($tmpfile)) {
                if (!unlink($tmpfile)) {
                  echo 'Failed removing temporary file: '.$tmpfile."\n";
                }
              }
              continue;
            }

            if (!file_exists($tmpfile)) {
              echo '- Copy does not exist'."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_COPY_MISSING).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              continue;
            }

            # md5 check
            $md5 = md5_file($tmpfile);
            if ($md5 === false) {
              echo '- MD5 hash failed, file: '.$tmpfile."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_MD5_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              if (file_exists($tmpfile)) {
                if (!unlink($tmpfile)) {
                  echo 'Failed removing temporary file: '.$tmpfile."\n";
                }
              }
              continue;
            }

            if (strlen($row['md5_hash']) && $md5 != $row['md5_hash']) {
              echo '- MD5 mismatches, '.$md5.' vs '.$row['md5_hash'].', file: '.$tmpfile."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_MD5_MISMATCH).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              if (file_exists($tmpfile)) {
                if (!unlink($tmpfile)) {
                  echo 'Failed removing temporary file: '.$tmpfile."\n";
                }
              }
              continue;
            }

            # group check
            if (filegroup($srcfile) != filegroup($tmpfile)) {
              if (!chgrp($tmpfile, filegroup($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHGRP_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo '- Failed changing group'."\n";
                $failed++;
                if (file_exists($tmpfile)) {
                  if (!unlink($tmpfile)) {
                    echo 'Failed removing temporary file: '.$tmpfile."\n";
                  }
                }
                continue;
              }
            }

            # permissions check
            if (fileperms($srcfile) != fileperms($tmpfile)) {
              if (!chmod($tmpfile, fileperms($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHMOD_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo '- Failed changing permissions'."\n";
                $failed++;
                if (file_exists($tmpfile)) {
                  if (!unlink($tmpfile)) {
                    echo 'Failed removing temporary file: '.$tmpfile."\n";
                  }
                }
                continue;
              }
            }

            # owner check
            if (fileowner($srcfile) != fileowner($tmpfile)) {
              if (!chown($tmpfile, fileowner($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo '- Failed changing ownership'."\n";
                $failed++;
                if (file_exists($tmpfile)) {
                  if (!unlink($tmpfile)) {
                    echo 'Failed removing temporary file: '.$tmpfile."\n";
                  }
                }
                continue;
              }
            }

            # modify time check
            if (filemtime($srcfile) != filemtime($tmpfile)) {
              if (!touch($tmpfile, filemtime($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo '- Failed changing modify time'."\n";
                $failed++;
                if (file_exists($tmpfile)) {
                  if (!unlink($tmpfile)) {
                    echo 'Failed removing temporary file: '.$tmpfile."\n";
                  }
                }
                continue;
              }
            }

            # check again in case one or more changed above
            if (
                filegroup($srcfile) != filegroup($tmpfile) ||
                filemtime($srcfile) != filemtime($tmpfile) ||
                fileowner($srcfile) != fileowner($tmpfile) ||
                fileperms($srcfile) != fileperms($tmpfile) ||
                filesize($srcfile) != filesize($tmpfile)
            ) {
              echo '- Metadata mismatches'."\n";
              echo '  Group    : '.filegroup($srcfile).' vs '.filegroup($tmpfile)."\n";
              echo '  Owner    : '.fileowner($srcfile).' vs '.fileowner($tmpfile)."\n";
              echo '  Perms    : '.fileperms($srcfile).' vs '.fileperms($tmpfile)."\n";
              echo '  Size     : '.filesize($srcfile).' vs '.filesize($tmpfile)."\n";
              echo '  Modified : '.filemtime($srcfile).' vs '.filemtime($tmpfile)."\n";

              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_META_MISMATCH).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              if (file_exists($tmpfile)) {
                if (!unlink($tmpfile)) {
                  echo 'Failed removing temporary file: '.$tmpfile."\n";
                }
              }
              continue;
            }

            # move file back
            $c = 'mv '.escapeshellarg($tmpfile).' '.escapeshellarg($srcfile);
            echo 'Run: '.$c."\n";
            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo '- mv command failed: '.implode(" ", $o)."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_EXEC_MV_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $failed++;
              echo "Temporary file is left at $tmpfile\n";
              exit(1); # this is fatal
            }

            $db->query('UPDATE files SET rewritten="'.date('Y-m-d H:i:s').'", status="'.mres(STATUS_REWRITTEN).'" WHERE id="'.mres($row['id']).'"');
            $rewritten++;
          }
          echo "Rewrote $rewritten files, $notfound not found, $failed failed\n";
      }
      break;
  }
}

?>
