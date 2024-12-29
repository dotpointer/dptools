<?php

# index, hash and rewrite files

# change log
# 2024-12-26 21:07

if (!extension_loaded('sqlite3')) {
  echo "PHP SQLite3 extension not loaded\n";
  exit(1);
}

define('FILENAME_DB', '.rewriter.sqlite3');
define('REWRITE_TIME_LIMIT', 86400 * 365 * 4);

# status codes
define('STATUS_ERROR_HASHING_FAILED', -15);
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
define('STATUS_ERROR_REWRITE_SOURCE_NOT_WRITABLE', -16);
define('STATUS_ERROR_REWRITE_TMP_NOT_WRITABLE', -17);
define('STATUS_NONE', 0);
define('STATUS_UNVERIFIED', 1);
define('STATUS_VERIFIED', 2);
define('STATUS_REWRITTEN', 3);

$statuses = array(
  STATUS_ERROR_HASHING_FAILED => 'Error, hashing, failed calculating MD5',
  STATUS_ERROR_REWRITE_EXEC_CP_FAILED => 'Error, rewrite, failed executing cp',
  STATUS_ERROR_REWRITE_EXEC_MV_FAILED => 'Error, rewrite, failed executing mv',
  STATUS_ERROR_REWRITE_COPY_MISSING => 'Error, rewrite, file copy missing',
  STATUS_ERROR_REWRITE_MD5_FAILED => 'Error, rewrite, failed calculating MD5',
  STATUS_ERROR_REWRITE_MD5_MISMATCH => 'Error, rewrite, MD5 on file and in database mismatches',
  STATUS_ERROR_REWRITE_META_MISMATCH => 'Metadata mismatches',
  STATUS_ERROR_REWRITE_SET_CHGRP_FAILED => 'Error, rewrite, failed setting group',
  STATUS_ERROR_REWRITE_SET_CHMOD_FAILED => 'Error, rewrite, failed setting permissions',
  STATUS_ERROR_REWRITE_SET_CHOWN_FAILED => 'Error, rewrite, failed setting owner',
  STATUS_ERROR_REWRITE_SIZE_CHECK_FAILED => 'Error, rewrite, failed size check',
  STATUS_ERROR_REWRITE_SOURCE_NOT_WRITABLE => 'Error, rewrite, source file not writable',
  STATUS_ERROR_REWRITE_TMP_NOT_WRITABLE => 'Error, rewrite, temporary file not writable',
  STATUS_ERROR_REWRITE_DISK_FREE_SPACE_CHECK_FAILED => 'Error, rewrite, failed disk free space check',
  STATUS_ERROR_REWRITE_FILE_LARGER_THAN_DISK_FREE_SPACE => 'Error, rewrite, file larger than free disk space',
  STATUS_ERROR_MISSING => 'Missing',
  STATUS_ERROR_MD5_MISMATCH => 'Error, MD5 mismatches',
  STATUS_NONE => 'None',
  STATUS_UNVERIFIED => 'Unverified',
  STATUS_VERIFIED => 'Verified',
  STATUS_REWRITTEN => 'Rewritten',
);

$config_dbpath = false;

$opts = getopt('cd:hi:o:r:sw:');

function get_db_path() {
  global $config_dbpath;
  if (!$config_dbpath) {
    $config_dbpath = locate_db(getcwd());
  }
  return $config_dbpath;
}

function get_db_conn() {
  return new SQLite3(get_db_path());
}

function get_line_clear($s) {
  return str_repeat(' ', strlen($s))."\r";
}

# get header
function getheader($i, $linecount, $stats, $text) {
  $s = '['.
    str_pad($i, strlen($linecount), ' ', STR_PAD_LEFT).'/'.$linecount.' '.
    str_pad(round($i / $linecount *  100), 3, ' ', STR_PAD_LEFT).'%';
  foreach ($stats as $k => $v) {
    $s .= ' '.str_pad($v, strlen($linecount), ' ', STR_PAD_LEFT).' '.$k;
  }
  $s .= '] '.$text;
  return $s;
}

# get file path relative to cwd
function get_file_path_relative_to_cwd($root_relative_filepath, $path_difference_root_cwd_cutoff) {
  $relativepath = './';

  if (strpos($root_relative_filepath, '/') !== false) {
    $dbfilepath = trim(dirname($root_relative_filepath), "/"); # ccc/ddd
    $parts = explode("/", $dbfilepath); # [ccc, ddd]

    if ($path_difference_root_cwd_cutoff > 0) {
      array_splice($parts, 0, $path_difference_root_cwd_cutoff);
    }

    if (count($parts)) {
      $relativepath = implode("/", $parts);
      if (strlen($relativepath)) $relativepath .= '/';
    }
  }
  return $relativepath;
}

# get difference between root and cwd paths
function get_root_cwd_path_difference($rootpath, $cwdpath) {
  $rootparts = explode("/", trim($rootpath, "/")); # [aaa, bbb]
  $cwdparts = explode("/", trim($cwdpath, "/"));   # [aaa, bbb, ccc, ddd]
  $diffparts = $cwdparts;
  array_splice($diffparts, 0, count($rootparts)); # [ccc, ddd]

  $pathdiff = implode("/", $diffparts);           # ccc/ddd
  if (strlen($pathdiff)) $pathdiff .= '/';        # ccc/ddd/
  return array(
    'path' => $pathdiff,
    'cutoff' => count($diffparts)
  );
}

# find database file, only 1 allowed
function locate_db($dbpath) {
  echo "Locating database... ";
  $path = realpath($dbpath); # /aaa/bbb/ccc
  $parts = explode("/", trim($path, "/")); # [aaa, bbb, ccc]
  $candidates = array();
  $pop = true;
  while ($pop) {
    $pathpart = '/'.implode("/", $parts);    # /aaa/bbb/ccc
    if (count($parts) > 0) $pathpart .= '/'; # /aaa/bbb/ccc/
    $path = $pathpart.FILENAME_DB;           # /aaa/bbb/ccc/rewrite.sqlite3
    if (file_exists($path)) {
      $candidates[] = $path;
    }
    if (!count($parts)) $pop = false;
    array_pop($parts); # [aaa, bbb]
  }

  if (count($candidates) < 1) {
    echo "none found in ".$dbpath."\n";
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

function remove_tmpfile($f, $header) {
  if (file_exists($f) && !unlink($f)) {
    echo get_line_clear($header);
    echo "* Failed removing temporary file - $f\n";
    exit(1);
  }
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
      if (!$config_dbpath) {
        $config_dbpath = './'.FILENAME_DB;
      }
      if (file_exists($config_dbpath)) {
        echo 'File already exists: '.$config_dbpath."\n";
        break;
      }
      $db = new SQLite3($config_dbpath);
      if (!$db || !file_exists($config_dbpath)) {
        echo 'Failed creating '.$config_dbpath."\n";
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

      echo "Created ".realpath($config_dbpath).' '.filesize($config_dbpath)." b \n";
      break;
    case 'd': # set database file
      if (substr($v, -1) === '/') {
        $b = FILENAME_DB;
        $dir = realpath($v);
      } else {
        $b = basename($v);
        $dir = realpath(dirname($v));
      }
      if (!file_exists($dir) || !is_dir($dir)) {
        echo "Database file directory not found or not a directory: $dir\n";
        exit(1);
      }
      $dir .= substr($dir, -1) != '/' ? '/' : '';
      $config_dbpath = $dir.$b;
      echo "Database file: ".$config_dbpath."\n";
      break;
    case 'h':
?>Usage: <?php echo basename($argv[0]); ?> <parameters>
Parameters:
  -c        Create <?php echo FILENAME_DB ?> SQLite3 file in current directory
  -d<path>  Use other database file, defaults to <?php echo FILENAME_DB ?> in current directory
  -h        Print this help
  -i<path>  Import MD5 file to database from <path>
  -o<path>  Output MD5 file with files in current directory found in database to <path>
  -r<option>
    option:
      i   Index files in current directory to database
      h   Hash files in current directory found in database
      v   Validate MD5 hashes on files in current directory
      w   Rewrite files in current directory found in database
  -s      Print details about the database and its contents
  -w<path>  Change working directory to <path>
<?php
      break;
    case 'i': # import md5, parts are taken from md5filecheck

      $db = get_db_conn();

      # get path difference, db vs cwd
      $root = trim(dirname(realpath($config_dbpath)), "/");
      $cwd = trim(getcwd(), "/");
      # root: aaa/bbb
      # cwd : aaa/bbb/ccc/ddd
      if (strpos($cwd, $root) !== 0) {
        echo "Cannot find root path in current directory path";
        exit(1);
      }

      $diff = get_root_cwd_path_difference($root, $cwd);
      $pathdiff = $diff['path'];
      $cutoff = $diff['cutoff']; # difference between root and current dir

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
        preg_match('/([a-zA-Z0-9]+)  (.*)\r?\n?/', $line, $matches); # <md5sum>  <filename>

        if (!isset($matches[1], $matches[2])) {
          echo 'Failed splitting line '.$i."\n";
          continue;
        }

        $md5 = $matches[1];
        $fullpath = realpath($matches[2]); # /aaa/bbb/ccc/dddfile
        $filesize = filesize($fullpath);

        echo get_line_clear($header);
        $header = getheader($i, $linecount, $stats, 'Importing '.$fullpath.' '.$filesize.' b');
        echo $header."\r";

        if (!file_exists($fullpath)) {
          $currentstatus = 'MISSING';
          $stats['missing']++;
        } else {

          $currentstatus = 'OK';
          $basename = basename($fullpath); # file
          $dirname = trim(dirname($fullpath), "/"); # aaa/bbb

          if (strpos($dirname, $root) !== 0) { # aaa/bbb/ccc/ddd must start with aaa/bbb
            $currentstatus = 'OUTSIDE ROOT';
            $stats['outside root']++;
          } else {

            $diff = get_root_cwd_path_difference($root, $cwd);
            $pathdiff = $diff['path'];
            $cutoff = $diff['cutoff']; # difference between root and current dir

            $relativepath = $pathdiff.$basename;           # /ccc/ddd/file

            $r = $db->query('SELECT * FROM files WHERE name="'.mres(ltrim($relativepath, "./")).'"');
            if (!sqlite3_num_rows($r)) {
              if (!$db->query('INSERT INTO files (
                  name, md5_hash, md5_created, status
                ) VALUES(
                  "'.mres($relativepath).'", "'.mres($md5).'", "'.mres($modified).'", "'.mres(STATUS_UNVERIFIED).'"
                )')) exit(1);
              $stats['inserted']++;
              $currentstatus = 'ADD';
            } else {
              while ($row = $r->fetchArray()) {
                if ($row['md5_hash'] == null || !strlen($row['md5_hash'])) {
                  if (!$db->query('
                    UPDATE files SET md5_hash="'.mres($md5).'" WHERE id="'.mres($row['id']).'"
                  ')) exit(1);
                  $stats['updated']++;
                  $currentstatus = 'UPDATE';
                }
                break; # only first
              }
            }
          }
        }
        # clear previous line
        echo get_line_clear($header);
        # print result
        $header = getheader($i, $linecount, $stats, $currentstatus.' '.$fullpath."\r");
        echo $header;
      }
      echo get_line_clear($header);
      # print result
      echo getheader($i, $linecount, $stats, "\r")."\n";
      fclose($f);

      break;
    case 'o': # output md5
      $db = get_db_conn();

      $r = $db->query("SELECT * FROM files ORDER BY name");

      echo "Writing to $v\n";
      $f = fopen($v, 'w');
      if (!$f) {
        echo "Could not open $v for writing\n";
        exit(1);
      }

      $i=0;
      $l = "";
      $total = sqlite3_num_rows($r);
      $written = 0;
      $missingmd5 = 0;
      while($row = $r->fetchArray()) {
        $md5 = $row['md5_hash'];
        if (!strlen($row['md5_hash'])) {
          $md5 = 0;
          $missingmd5++;
        }
        if (!fputs($f, $l.$md5.'  '.$row['name'])) {
          echo "Failed writing line $i to $v\n";
          exit(1);
        }
        if (!$i) {
          $l = "\n";
        }
        $i++;
        $written++;
      }
      fclose($f);
      echo "$total files found, $written written to file, $missingmd5 without MD5 hashes\n";
      if ($missingmd5) {
          echo "Warning! $missingmd5 files has no MD5 hashes, used 0 as MD5 for those\n";
      }
      break;
    case 'r': # re-something

      $db = get_db_conn();
      $cwd = realpath(getcwd());
      $root = trim(dirname(realpath($config_dbpath)), "/");

      switch ($v) {
        case 'i': # index
          echo "Indexing files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          $c = 'find . -type f ! -name \''.FILENAME_DB.'\'|sort';
          exec($c, $o, $r);
          if ($r !== 0) {
            echo 'Failed: '.$c.": \n".implode("\n", $o)."\n";
            exit(1);
          }


          $header = '';
          $stats = array(
            'added' => 0
          );
          $total = count($o);

          $i=0;
          foreach ($o as $k1 => $v1) {
            $i++;
            $v1 = $pathdiff.ltrim($v1, './');

            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, 'Adding '.$v1);
            echo $header."\r";

            $r = $db->query('SELECT * FROM files WHERE name="'.SQLite3::escapeString($v1).'"');
            if (!sqlite3_num_rows($r)) {
              $currentstatus = 'ADDED';
              if (!$db->query('INSERT INTO files (name, status) VALUES("'.SQLite3::escapeString($v1).'", "'.mres(STATUS_UNVERIFIED).'")')) exit(1);
              $stats['added']++;
            } else {
              $currentstatus = 'CHECKED';
            }

            # clear previous line
            echo get_line_clear($header);
            # print result
            $header = getheader($i, $total, $stats, $currentstatus.' '.$v1."\r");
            echo $header;
          }

          echo get_line_clear($header);
          # print result
          echo getheader($i, $total, $stats, "$total files found, added ".$stats['added']."\r")."\n";
          break;
        case 'h': # hash
          echo "Hashing files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          # this gives file paths relative to dbpath
          $sql = 'SELECT * FROM files WHERE name LIKE "'.SQLite3::escapeString($pathdiff).'%" AND (md5_hash IS NULL OR md5_hash = "")';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No indexed unhashed files in $cwd\n";
            exit(1);
          }

          $total = sqlite3_num_rows($r);
          $header = '';
          $rehashed = 0;
          $stats = array(
            'missing' => 0,
            'failed' => 0,
            'hashed' => 0
          );

          $i=0;
          while($row = $r->fetchArray()) {
            $i++;
            $dbfilename = basename($row['name']);
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);
            $relativepath = $relativepath.$dbfilename;

            $filesize = filesize($relativepath);
            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, 'Hashing '.$relativepath.' '.$filesize.' b');
            echo $header."\r";

            if (!file_exists($relativepath)) {
              $currentstatus = 'MISSING';
              $stats['missing']++;
            } else {
              $md5 = md5_file($relativepath);
              if ($md5 !== false) {
                if (!$db->query('
                  UPDATE files
                  SET
                    md5_hash="'.mres($md5).'",
                    md5_created = "'.mres(date("Y-m-d H:i:s")).'",
                    status="'.mres(STATUS_VERIFIED).'"
                  WHERE id="'.mres($row['id']).'"')) exit(1);
                $currentstatus = 'HASHED';
                $stats['hashed']++;
              } else {
                if (!$db->query('
                  UPDATE files
                  SET
                    md5_hash="'.mres($md5).'",
                    md5_created = "'.mres(date("Y-m-d H:i:s")).'",
                    status="'.mres(STATUS_ERROR_HASHING_FAILED).'"
                  WHERE id="'.mres($row['id']).'"')) exit(1);
                $currentstatus = 'HASHING FAILED';
                $stats['failed']++;
              }
            }
            # clear previous line
            echo get_line_clear($header);
            # print result
            $header = getheader($i, $total, $stats, $currentstatus.' '.$relativepath."\r");
            echo $header;

          }
          echo get_line_clear($header);
          # print result
          echo getheader($i, $total, $stats, "\r")."\n";
          break;
        case 'v':
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

          echo "Verifying files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

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

          $header = '';
          # loop files
          while ($row = $r->fetchArray()) {

            $i++;

            $md5 = $row['md5_hash'];

            $dbfilename = basename($row['name']);
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);
            $path = $relativepath.$dbfilename;

            $filesize = filesize($path);
            echo get_line_clear($header);
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
            echo get_line_clear($header);
            # print result
            $header = getheader($i, $linecount, $stats, $currentstatus.' '.$path."\r");

            if ($currentstatus !== 'OK' && $errorlog !== false) {
              logtext($fileerror, $errorlog, $header);
            }
            echo $header;
          }
          echo get_line_clear($header);
          # print result
          echo getheader($i, $linecount, $stats, "\r")."\n";

          if ($fileerror) fclose($fileerror);
          break;
        case 'w': # rewrite
          echo "Rewriting files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb
          # cwd   : aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) { # aaa/bbb/ccc/ddd must begin with aaa/bbb
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          # this gives file paths relative to root path - ccc/ddd/file etc
          $sql = 'SELECT * FROM files WHERE name LIKE "'.SQLite3::escapeString(ltrim($pathdiff, "./")).'%"';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No indexed files found in $cwd\n";
            exit(1);
          }

          $total = sqlite3_num_rows($r); # ." files in db and directory\n";

          $stats = array(
            'failed' => 0,
            'missing' => 0,
            'rewritten' => 0
          );

          $header = '';
          $currentstatus = '';
          $i=0;
          while($row = $r->fetchArray()) {
            $i++;
            # check if already rewritten and no need to redo
            if ($row['rewritten'] != null && strlen($row['rewritten']) > 0 &&
              strtotime($row['rewritten']) > (time() - REWRITE_TIME_LIMIT)) {
              continue;
            }

            $dbfilename = basename($row['name']); # file
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);

            $tmpfile = $relativepath.'.rewrite-tmp';
            $srcfile = $relativepath.$dbfilename;

            $filesize = filesize($srcfile);
            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, 'Rewriting '.$srcfile.' '.$filesize.' b');
            echo $header."\r";

            if (!file_exists($srcfile)) {
              echo get_line_clear($header);
              echo "* Not found - $srcfile\n";
              $currentstatus = 'MISSING';
              $stats['missing']++;
              continue;
            }

            if (!is_writable($srcfile)) {
              echo get_line_clear($header);
              echo "* Source not writable - $srcfile\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SOURCE_NOT_WRITABLE).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            if (!is_writable($relativepath)) {
              echo get_line_clear($header);
              echo "* Temporary file not writable - $tmpfile\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_TMP_NOT_WRITABLE).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # get file size
            $size = filesize($srcfile);
            if ($size === false) {
              echo get_line_clear($header);
              echo "* Size check failed - $srcfile\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SIZE_CHECK_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # get free space
            $freespace = disk_free_space($relativepath);
            if ($freespace === false) {
              echo get_line_clear($header);
              echo "*  Disk free space check failed - $relativepath\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_DISK_FREE_SPACE_CHECK_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              exit(1); # fatal
            }

            # check file size vs free space
            if ($size > $freespace) {
              echo get_line_clear($header);
              echo "* No free space to cp, $freespace.' b free, need $size b - $srcfile\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_FILE_LARGER_THAN_DISK_FREE_SPACE).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # echo "Copying $srcfile to $tmpfile\n";

            $c = 'cp '.escapeshellarg($srcfile).' '.escapeshellarg($tmpfile);
            # echo 'Run: '.$c."\n";
            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo get_line_clear($header);
              echo '* cp command '.$c.' failed: '.implode(" ", $o)."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_EXEC_CP_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            if (!file_exists($tmpfile)) {
              echo get_line_clear($header);
              echo "* Copy missing - $tmpfile"."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_COPY_MISSING).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # md5 check
            $md5 = md5_file($tmpfile);
            if ($md5 === false) {
              echo get_line_clear($header);
              echo "* MD5 hash failed, file: $tmpfile ($srcfile)\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_MD5_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            if (strlen($row['md5_hash']) && $md5 != $row['md5_hash']) {
              echo get_line_clear($header);
              echo "* MD5 mismatches, $md5 vs ".$row['md5_hash'].", file: $tmpfile ($srcfile)\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_MD5_MISMATCH).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # group check
            if (filegroup($srcfile) != filegroup($tmpfile)) {
              if (!chgrp($tmpfile, filegroup($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHGRP_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo get_line_clear($header);
                echo "* Failed changing group - $tmpfile ($srcfile)\n";
                $currentstatus = 'FAILED';
                $stats['failed']++;
                remove_tmpfile($tmpfile, $header);
                continue;
              }
            }

            # permissions check
            if (fileperms($srcfile) != fileperms($tmpfile)) {
              if (!chmod($tmpfile, fileperms($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHMOD_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo get_line_clear($header);
                echo "* Failed changing permissions - $tmpfile ($srcfile)\n";
                $stats['failed']++;
                remove_tmpfile($tmpfile, $header);
                continue;
              }
            }

            # owner check
            if (fileowner($srcfile) != fileowner($tmpfile)) {
              if (!chown($tmpfile, fileowner($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo get_line_clear($header);
                echo "* Failed changing ownership - $tmpfile ($srcfile)\n";
                $stats['failed']++;
                remove_tmpfile($tmpfile, $header);
                continue;
              }
            }

            # modify time check
            if (filemtime($srcfile) != filemtime($tmpfile)) {
              if (!touch($tmpfile, filemtime($srcfile))) {
                if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
                echo get_line_clear($header);
                echo "* Failed changing modify time - $tmpfile ($srcfile)\n";
                $stats['failed']++;
                remove_tmpfile($tmpfile, $header);
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
              echo get_line_clear($header);
              echo "* Metadata mismatches - $tmpfile ($srcfile)\n";
              echo '  Group    : '.filegroup($srcfile).' vs '.filegroup($tmpfile)."\n";
              echo '  Owner    : '.fileowner($srcfile).' vs '.fileowner($tmpfile)."\n";
              echo '  Perms    : '.fileperms($srcfile).' vs '.fileperms($tmpfile)."\n";
              echo '  Size     : '.filesize($srcfile).' vs '.filesize($tmpfile)."\n";
              echo '  Modified : '.filemtime($srcfile).' vs '.filemtime($tmpfile)."\n";

              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_META_MISMATCH).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # move file back
            $c = 'mv '.escapeshellarg($tmpfile).' '.escapeshellarg($srcfile);
            # echo 'Run: '.$c."\n";
            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo get_line_clear($header);
              echo "* mv command $c failed: ".implode(" ", $o)."\n";
              if (!$db->query('UPDATE files SET status="'.mres(STATUS_ERROR_REWRITE_EXEC_MV_FAILED).'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $stats['failed']++;
              echo "Temporary file is left at $tmpfile\n";
              exit(1); # this is fatal
            }

            $db->query('UPDATE files SET rewritten="'.date('Y-m-d H:i:s').'", status="'.mres(STATUS_REWRITTEN).'" WHERE id="'.mres($row['id']).'"');
            $currentstatus = 'Rewrote';
            $stats['rewritten']++;

            # clear previous line
            echo get_line_clear($header);
            # print result
            $header = getheader($i, $total, $stats, $currentstatus.' '.$srcfile."\r");
            echo $header;
          }
          echo get_line_clear($header);
          # print result
          echo getheader($i, $total, $stats, 'Rewrote '.$stats['rewritten'].' files, '.$stats['missing'].' missing, '.$stats['failed'].' failed'."\r")."\n";
          break;
      } # re-switch
      break;
    case 's': # stats
      $db = get_db_conn();
      $dbpath = get_db_path();
      echo 'Database: '.$dbpath.' '.filesize($dbpath)." b\n";
      echo 'Free space: '.disk_free_space(dirname($dbpath))." b\n";
      echo "Files:\n";
      $r = $db->query('SELECT status, COUNT(id) AS quantity FROM files GROUP BY status ORDER BY status');
      $w = 0;
      $result = array();
      while ($row = $r->fetchArray()) {
        $result[] = $row;
        $w = strlen($row['quantity']) > $w ? strlen($row['quantity']) : $w;
      }

      foreach ($result as $row) {
        echo ' * '.str_pad($row['quantity'], $w, ' ', STR_PAD_LEFT).' '.(array_key_exists($row['status'], $statuses) ? $statuses[$row['status']] : 'Unknown status '.$row['status'])."\n";
      }
      break;
    case 'w': # set working directory
      $d = realpath($v);
      if (!chdir($d)) {
        echo 'Failed changing working directory to '.$d."\n";
        exit(1);
      }
      echo 'Working directory set to '.$d."\n";
      break;
  } # switch
}

?>
