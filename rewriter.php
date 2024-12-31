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

# log message types
define('LOG_TYPE_REWRITE', 1);

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
define('STATUS_ERROR_UNLINK_ORIGINAL_FILE_FAILED', -18);
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
  STATUS_ERROR_UNLINK_ORIGINAL_FILE_FAILED => 'Error, failed removing original file',
  STATUS_ERROR_MISSING => 'Missing',
  STATUS_ERROR_MD5_MISMATCH => 'Error, MD5 mismatches',
  STATUS_UNVERIFIED => 'Unverified',
  STATUS_VERIFIED => 'Verified',
  STATUS_REWRITTEN => 'Rewritten',
);

$config_backup_original_before_rewrite = true;
$config_dbpath = false;
$config_tmpdir = false;

$opts = getopt('chi:o:p:r:sw:');

function get_db_conn() {
  return new SQLite3(get_db_path());
}

function get_db_path() {
  global $config_dbpath;
  if (!$config_dbpath) {
    echo "Locating database... ";
    $candidates = locate_db(getcwd());
    $n = count($candidates);
    if ($n < 1) {
        echo "none found in ".$dbpath."\n";
        exit(1);
    } else if (count($candidates) > 1) {
      echo "multiple found in ".$dbpath.":\n";
      foreach ($candidates as $c) {
        echo '- '.$c."\n";
      }
      echo "Please use -pd<path> to specify which one to use.\n";
      exit(1);
    }
    echo $candidates[0]."\n";
    $config_dbpath = $candidates[0];
  }
  return $config_dbpath;
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

function get_formatted_logmessage_range($logmessage) {
  $a = array();
  foreach ($logmessage as $v) {
    $a[] = $v['min'] === $v['max'] ? $v['min'] : $v['min'].'-'.$v['max'];
  }
  return implode(",", $a);
}

function get_line_clear($s) {
  return str_repeat(' ', strlen($s))."\r";
}

function get_logmessage_range($logmessage, $id) {
  $found = false;
  foreach ($logmessage as $index => $range) {
    if ($id === $range['min'] - 1) {
      $logmessage[$index]['min'] = $id;
      $found = true;
      break;
    } else if ($range['max'] + 1 === $id) {
      $logmessage[$index]['max'] = $id;
      $found = true;
      break;
    } else if ($id >= $range['min'] && $id <= $range['max']) {
      $found = true;
      break;
    }
  }

  if (!$found) {
    $logmessage[] = array('min' => $id, 'max' => $id);
  }

  sort($logmessage);

  $new_logmessage = array();
  $newindex = 0;
  foreach ($logmessage as $index => $range) {
    # first one
    if (!count($new_logmessage)) {
      $new_logmessage[] = $range;
    # this range max + 1 equals last range min
    } else if ($range['max'] + 1 === $new_logmessage[$newindex]['min']) {
      $new_logmessage[$newindex]['min'] = $range['min'];
    # last range max + 1 equals this range min
    } else if ($new_logmessage[$newindex]['max'] + 1 === $range['min']) {
      $new_logmessage[$newindex]['max'] = $range['max'];
    # not fitting anywhere
    } else {
      $newindex++;
      $new_logmessage[$newindex] = $range;
    }
  }

  return $new_logmessage;
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

function get_si_size($bytes) {
  $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $base = 1024;
  $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
  return sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class];
}

function locate_db($dbpath) {
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
  return $candidates;
}

function mres($s) {
  return SQLite3::escapeString($s);
}

function mresnow() {
  return mres(date('Y-m-d H:i:s'));
}

function remove_tmpfile($f, $header) {
  if (file_exists($f) && !unlink($f)) {
    echo get_line_clear($header);
    echo "* Failed removing temporary file - $f\n";
    exit(1);
  }
}

function sqlite3_num_rows($r) {
  if ($r === false) return 0;
  $n = 0;
  while($row = $r->fetchArray()) {
    ++$n;
  }
  return $n;
}

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
      $dir = realpath(dirname($config_dbpath));
      $candidates = locate_db($dir);
      if (count($candidates)) {
        echo 'Databases found in the file tree: '.implode(", ", $candidates)."\n";
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
          md5 TEXT,
          rewrites INTEGER DEFAULT 0,
          status INTEGER DEFAULT 0,
          created TEXT,
          hashed TEXT,
          rewritten TEXT,
          updated TEXT
        )')) exit(1);

      if (!$db->query(
        'CREATE TABLE log (
          id INTEGER PRIMARY KEY,
          ids_files TEXT,
          type INTEGER,
          message TEXT,
          created TEXT,
          updated TEXT
        )')) exit(1);

      echo "Created ".realpath($config_dbpath).' ('.get_si_size(filesize($config_dbpath)).")\n";
      break;
    case 'p': # set property
      $subs = is_array($v) ? $v : array($v);
      foreach ($subs as $sub) {
        $subcase = substr($sub, 0, 1);
        $subv = substr($sub, 1);
        switch ($subcase) {
          case 'd': # set database file
            if (substr($subv, -1) === '/') {
              $b = FILENAME_DB;
              $dir = realpath($subv);
            } else {
              $b = basename($subv);
              $dir = realpath(dirname($subv));
            }
            if (!file_exists($dir) || !is_dir($dir)) {
              echo "Database file directory not found or not a directory: $dir\n";
              exit(1);
            }
            $dir .= substr($dir, -1) != '/' ? '/' : '';
            $config_dbpath = $dir.$b;
            echo "Database file: ".$config_dbpath."\n";
            break;
          case 'o': # backup original on/off
            if (!is_numeric($subv)) {
              echo "Invalid option: -p$subcase$subv\n";
              exit(1);
            }
            $config_backup_original_before_rewrite = (int)$subv === 1;
            echo 'Backup original before rewrite: '.($config_backup_original_before_rewrite ? 'on' : 'off')."\n";
            break;
          case 't': # set temporary directory
            $dir = realpath($subv);
            if (!file_exists($dir) || !is_dir($dir)) {
              echo "Temporary files directory not found or not a directory: $subv\n";
              exit(1);
            }
            $dir .= substr($dir, -1) != '/' ? '/' : '';
            $config_tmpdir = $dir;
            echo "Temporary files directory: ".$config_tmpdir."\n";
            break;
          }
      }
      break;
    case 'h':
      echo basename($argv[0]); ?> is a program to re-magnetize files on magnetic disks.
It does so by recursively copying files to a temporary location and then copying them
back, preserving MD5 sums, timestamps, rights in the process using a SQLite3 database
for meta data storage.

Example of work flow to create database, index, hash and rewrite:
  -c (create database), -ri (index files), -i files.md5 (import MD5 from MD5 sums file),
  -rv (validate MD5 sums), -rh (hash files without MD5, -rw (rewrite files), -rv (validate)

Options are applied in the order they are supplied.

Usage: <?php echo basename($argv[0]); ?> <options>

Options:
  -c           Create <?php echo FILENAME_DB ?> SQLite3 file in current directory
  -h           Print this help
  -i<path>     Import MD5 file to database from <path>
  -o<path>     Output MD5 file with files in current directory found in database to <path>
  -p<n><value> Set property with name <n> to value <value>
               Put option and path together, like this: -de/path/
    property <n>:
      d        Path, set database file, default is <?php echo FILENAME_DB ?> in current directory
      o        0/1, rewrite, backup original file to file.original.n.rewrite before rewrite
               before rewriting, default 1
      t        Path, set temporary files directory, default is same as original file
  -r<o>        Run the task with option name <o>
    option <o>:
      i   Index files in current directory to database
      h   Hash files in current directory found in database
      v   Validate MD5 hashes on files in current directory
      w   Rewrite files in current directory found in database
  -s           Print details about the database and its contents
  -w<path>     Change working directory to <path>
<?php
      break;
    case 'i': # import md5, parts are taken from md5filecheck

      $db = get_db_conn();
      $root = trim(dirname(realpath($config_dbpath)), "/");
      $cwd = trim(realpath(getcwd()), "/");

      # root: aaa/bbb, cwd : aaa/bbb/ccc/ddd
      if (strpos($cwd, $root) !== 0) {
        echo "Cannot find root path in current directory path";
        exit(1);
      }

      $diff = get_root_cwd_path_difference($root, $cwd);
      $pathdiff = $diff['path'];
      $cutoff = $diff['cutoff']; # difference between root and current dir

      $file = realpath($v);
      $diff_root_file = get_root_cwd_path_difference($root, dirname($file));

      if (!file_exists($file)) {
        echo 'File not found: '.$file."\n";
        exit(1);
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
        echo 'Failed opening '.$file."\n";
        exit(1);
      }

      while(!feof($f)){
        $line = fgets($f, 4096);
        $linecount = $linecount + substr_count($line, PHP_EOL);
      }

      if (!rewind($f)) {
        echo 'Failed rewinding '.$file."\n";
        fclose($f);
        exit(1);
      }

      $i = 0;
      $stats = array(
        'inserted' => 0,
        'updated' => 0,
        'missing' => 0
      );
      $header = '';
      while ($line = fgets($f)) {

        $i++;
        preg_match('/([a-zA-Z0-9]+)  (.*)\r?\n?/', $line, $matches); # <md5sum>  <filename>

        if (!isset($matches[1], $matches[2])) {
          echo 'Failed splitting line '.$i."\n";
          continue;
        }

        $md5 = $matches[1];
        $relativepath = $matches[2];

        if (!file_exists($relativepath)) {
          $currentstatus = 'MISSING';
          $stats['missing']++;
        } else {

          $filesize = file_exists($relativepath) ? filesize($relativepath) : 0;
          echo get_line_clear($header);
          $header = getheader($i, $linecount, $stats, 'Importing '.$relativepath.' ('.get_si_size($filesize).')');
          echo $header."\r";

          $currentstatus = 'OK';
          $basename = basename($relativepath); # file
          $dirname = trim(dirname(realpath($relativepath)), "/"); # aaa/bbb

          if (strpos($dirname, $root) !== 0) { # aaa/bbb/ccc/ddd must start with aaa/bbb
            $currentstatus = 'OUTSIDE ROOT';
            if (!isset($stats['outside root'])) $stats['outside root'] = 0;
            $stats['outside root']++;
          } else {

            $relativepath = $diff_root_file['path'].$relativepath;
            $sql = 'SELECT * FROM files WHERE name="'.mres(ltrim($relativepath, "./")).'"';

            $r = $db->query($sql);
            if (!sqlite3_num_rows($r)) {
              if (!$db->query('INSERT INTO files (
                  name, md5, status, created, hashed
                ) VALUES(
                  "'.mres($relativepath).'", "'.mres($md5).'", "'.mres(STATUS_UNVERIFIED).'", "'.mresnow().'", "'.mres($modified).'"
                )')) exit(1);
              $stats['inserted']++;
              $currentstatus = 'ADD';
            } else {
              while ($row = $r->fetchArray()) {
                if ($row['md5'] == null || !strlen($row['md5'])) {
                  if (!$db->query('
                    UPDATE files SET md5 = "'.mres($md5).'", updated = "'.mresnow().'" WHERE id = "'.mres($row['id']).'"
                  ')) exit(1);
                  $stats['updated']++;
                  $currentstatus = 'UPDATE';
                }
                break; # only first
              }
            }
          }
        }
        echo get_line_clear($header);
        $header = getheader($i, $linecount, $stats, $currentstatus.' '.$relativepath."\r");
        echo $header;
      } # while
      echo get_line_clear($header);
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

      $i = 0;
      $l = "";
      $total = sqlite3_num_rows($r);
      $written = 0;
      $missingmd5 = 0;
      while($row = $r->fetchArray()) {
        $md5 = $row['md5'];
        if (!strlen($row['md5'])) {
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

          # dbpath: aaa/bbb, cwd: aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          $c = 'find . -type f -regextype posix-extended ! -name \''.FILENAME_DB.'\' ! -regex ".*\.(copy|original)\.[0-9]+\.rewrite$" |sort';
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

          $i = 0;
          foreach ($o as $k1 => $v1) {
            $i++;
            $v1 = $pathdiff.ltrim($v1, './');

            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, 'Adding '.$v1);
            echo $header."\r";

            $r = $db->query('SELECT * FROM files WHERE name="'.mres($v1).'"');
            if (!sqlite3_num_rows($r)) {
              $currentstatus = 'ADDED';
              if (!$db->query('INSERT INTO files (name, status, created) VALUES("'.mres($v1).'", "'.mres(STATUS_UNVERIFIED).'", "'.mresnow().'")')) exit(1);
              $stats['added']++;
            } else {
              $currentstatus = 'CHECKED';
            }

            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, $currentstatus.' '.$v1."\r");
            echo $header;
          }
          echo get_line_clear($header);
          echo getheader($i, $total, $stats, "$total files found, added ".$stats['added']."\r")."\n";
          break;
        case 'h': # hash
          echo "Hashing files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb, cwd: aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          # get paths relative to dbpath
          $sql = 'SELECT * FROM files WHERE name LIKE "'.mres($pathdiff).'%" AND (md5 IS NULL OR md5 = "")';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No indexed unhashed files in $cwd\n";
            exit(1);
          }

          $total = sqlite3_num_rows($r);
          $header = '';
          $rehashed = 0;
          $stats = array(
            'hashed' => 0,
            'failed' => 0,
            'missing' => 0
          );

          $i = 0;
          while($row = $r->fetchArray()) {
            $i++;
            $dbfilename = basename($row['name']);
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);
            $relativepath = $relativepath.$dbfilename;

            if (!file_exists($relativepath)) {
              $currentstatus = 'MISSING';
              $stats['missing']++;
            } else {
              $filesize = filesize($relativepath);
              echo get_line_clear($header);
              $header = getheader($i, $total, $stats, 'Hashing '.$relativepath.' ('.get_si_size($filesize).')');
              echo $header."\r";

              $md5 = md5_file($relativepath);
              if ($md5 !== false) {
                if (!$db->query('
                  UPDATE files
                  SET
                    md5 = "'.mres($md5).'",
                    status = "'.mres(STATUS_VERIFIED).'",
                    updated = "'.mresnow().'",
                    hashed =  "'.mresnow().'"
                  WHERE id = "'.mres($row['id']).'"')) exit(1);
                $currentstatus = 'HASHED';
                $stats['hashed']++;
              } else {
                if (!$db->query('
                  UPDATE files
                  SET
                    md5 = "'.mres($md5).'",
                    status = "'.mres(STATUS_ERROR_HASHING_FAILED).'",
                    updated = "'.mresnow().'"
                  WHERE id = "'.mres($row['id']).'"')) exit(1);
                $currentstatus = 'HASHING FAILED';
                $stats['failed']++;
              }
            }
            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, $currentstatus.' '.$relativepath."\r");
            echo $header;
          }
          echo get_line_clear($header);
          echo getheader($i, $total, $stats, "\r")."\n";
          break;
        case 'v':

          echo "Verifying files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb, cwd: aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) {
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          $sql = 'UPDATE files SET status = "'.mres(STATUS_UNVERIFIED).'", updated = "'.mresnow().'" WHERE name LIKE "'.mres($pathdiff).'%"';
          if (!$db->query($sql)) exit(1);

          $sql = 'SELECT * FROM files WHERE name LIKE "'.mres($pathdiff).'%"';
          $r = $db->query($sql);
          $total = sqlite3_num_rows($r);
          if (!$total) {
            echo "No files found in $cwd\n";
            exit(1);
          }

          echo $total." unverified files\n";

          $i = 0;
          $stats = array(
            'ok' => 0,
            'unhashed' => 0,
            'mismatch' => 0,
            'missing' => 0
          );

          $header = '';
          while ($row = $r->fetchArray()) {
            $i++;
            $md5 = $row['md5'];
            $dbfilename = basename($row['name']);
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);
            $path = $relativepath.$dbfilename;

            if (!file_exists($path)) {
              $currentstatus = 'MISSING';
              $stats['missing']++;
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_MISSING).'" WHERE id="'.mres($row['id']).'"')) exit(1);
            } else {
              $filesize = filesize($path);
              echo get_line_clear($header);
              $header = getheader($i, $total, $stats, 'MD5-summing '.$path.' ('.get_si_size($filesize).')');
              echo $header."\r";
              if ($md5 == null || !strlen($md5)) {
                $currentstatus = 'UNHASHED';
                $stats['unhashed']++;
              } else {
                if (md5_file($path) === $md5) {
                  $stats['ok'] ++;
                  $currentstatus = 'OK';
                  if (!$db->query('UPDATE files SET status = "'.mres(STATUS_VERIFIED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
                } else {
                  $stats['mismatch'] ++;
                  $currentstatus = 'MISMATCH';
                  if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_MD5_MISMATCH).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
                }
              }
            }
            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, $currentstatus.' '.$path."\r");
            echo $header;
          }
          echo get_line_clear($header);
          echo getheader($i, $total, $stats, "\r")."\n";
          break;
        case 'w': # rewrite
          echo "Rewriting files in ".$cwd."\n";
          $cwd = trim($cwd, "/");

          # dbpath: aaa/bbb, cwd: aaa/bbb/ccc
          if (strpos($cwd, $root) !== 0) { # aaa/bbb/ccc/ddd must begin with aaa/bbb
            echo "Cannot find database path in current directory path\n";
            exit(1);
          }

          $diff = get_root_cwd_path_difference($root, $cwd);
          $pathdiff = $diff['path'];
          $cutoff = $diff['cutoff']; # difference between root and current dir

          $sql = 'INSERT INTO log (type, created) VALUES("'.mres(LOG_TYPE_REWRITE).'", "'.mresnow().'")';
          if (!$db->query($sql)) exit(1);
          $id_logs = $db->lastInsertRowID();
          if (!$id_logs) {
            echo "Failed creating log row\n";
            exit(1);
          }

          # get paths relative to root path - ccc/ddd/file etc
          $sql = 'SELECT * FROM files WHERE name LIKE "'.mres(ltrim($pathdiff, "./")).'%"';
          $r = $db->query($sql);
          if (!sqlite3_num_rows($r)) {
            echo "No indexed files found in $cwd\n";
            exit(1);
          }

          $total = sqlite3_num_rows($r);
          echo "$total files in db and directory\n";

          $stats = array(
            'rewritten' => 0,
            'failed' => 0,
            'missing' => 0
          );

          $header = '';
          $currentstatus = '';
          $i = 0;
          $logmessage_range = array();
          while($row = $r->fetchArray()) {
            $i++;
            # check if already rewritten and no need to redo
            if ($row['rewritten'] != null && strlen($row['rewritten']) > 0 &&
              strtotime($row['rewritten']) > (time() - REWRITE_TIME_LIMIT)) {
              continue;
            }

            $dbfilename = basename($row['name']); # file
            $relativepath = get_file_path_relative_to_cwd($row['name'], $cutoff);

            $copydir = $config_tmpdir === false ? $relativepath : $config_tmpdir;

            $j = 0;
            do {
              $j++;
              $tmpfile = $copydir.$dbfilename.'.copy.'.$j.'.rewrite';
            } while (file_exists($tmpfile));

            $srcfile = $relativepath.$dbfilename;

            if (!file_exists($srcfile)) {
              echo get_line_clear($header);
              echo "* Not found - $srcfile\n";
              $currentstatus = 'MISSING';
              $stats['missing']++;
              continue;
            }

            $filesize = filesize($srcfile);
            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, 'Rewriting '.$srcfile.' ('.get_si_size($filesize).')');
            echo $header."\r";

            if (!is_writable($srcfile)) {
              echo get_line_clear($header);
              echo "* Source not writable - $srcfile\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SOURCE_NOT_WRITABLE).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            if (!is_writable($relativepath)) {
              echo get_line_clear($header);
              echo "* Temporary file not writable - $tmpfile\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_TMP_NOT_WRITABLE).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # get file size
            $size = filesize($srcfile);
            if ($size === false) {
              echo get_line_clear($header);
              echo "* Size check failed - $srcfile\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SIZE_CHECK_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # get free space
            $freespace = disk_free_space($relativepath);
            if ($freespace === false) {
              echo get_line_clear($header);
              echo "*  Disk free space check failed - $relativepath\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_DISK_FREE_SPACE_CHECK_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              exit(1); # fatal
            }

            # check file size vs free space
            if ($size > $freespace) {
              echo get_line_clear($header);
              echo "* No free space to cp, $freespace.' b free, need $size b - $srcfile\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_FILE_LARGER_THAN_DISK_FREE_SPACE).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            $c = 'cp --sparse=always '.escapeshellarg($srcfile).' '.escapeshellarg($tmpfile);
            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo get_line_clear($header);
              echo '* cp command '.$c.' failed: '.implode(" ", $o)."\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_EXEC_CP_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            if (!file_exists($tmpfile)) {
              echo get_line_clear($header);
              echo "* Copy missing - $tmpfile"."\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_COPY_MISSING).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            # md5 check
            $md5 = md5_file($tmpfile);
            if ($md5 === false) {
              echo get_line_clear($header);
              echo "* MD5 hash failed, file: $tmpfile ($srcfile)\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_MD5_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            if (strlen($row['md5']) && $md5 != $row['md5']) {
              echo get_line_clear($header);
              echo "* MD5 mismatches, $md5 vs ".$row['md5'].", file: $tmpfile ($srcfile)\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_MD5_MISMATCH).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # group check
            if (filegroup($srcfile) != filegroup($tmpfile) &&
              !chgrp($tmpfile, filegroup($srcfile))) {
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SET_CHGRP_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              echo get_line_clear($header);
              echo "* Failed changing group - $tmpfile ($srcfile)\n";
              $currentstatus = 'FAILED';
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # permissions check
            if (fileperms($srcfile) != fileperms($tmpfile) &&
              !chmod($tmpfile, fileperms($srcfile))) {
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SET_CHMOD_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              echo get_line_clear($header);
              echo "* Failed changing permissions - $tmpfile ($srcfile)\n";
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # owner check
            if (fileowner($srcfile) != fileowner($tmpfile) &&
              !chown($tmpfile, fileowner($srcfile))) {
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              echo get_line_clear($header);
              echo "* Failed changing ownership - $tmpfile ($srcfile)\n";
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            # modify time check
            if (filemtime($srcfile) != filemtime($tmpfile) &&
              !touch($tmpfile, filemtime($srcfile))) {
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_SET_CHOWN_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              echo get_line_clear($header);
              echo "* Failed changing modify time - $tmpfile ($srcfile)\n";
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
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

              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_META_MISMATCH).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $stats['failed']++;
              remove_tmpfile($tmpfile, $header);
              continue;
            }

            if ($config_backup_original_before_rewrite) {
              $j = 0;
              do {
                $j++;
                $origfile = $relativepath.$dbfilename.'.original.'.$j.'.rewrite';
              } while (file_exists($origfile));

              # move source file to original file as a backup
              $c = 'mv '.escapeshellarg($srcfile).' '.escapeshellarg($origfile);

              unset($o, $r1);
              exec($c, $o, $r1);
              if ($r1 !== 0) {
                echo get_line_clear($header);
                echo "* mv command $c failed: ".implode(" ", $o)."\n";
                if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_EXEC_MV_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
                $stats['failed']++;
                echo "Temporary file is left at $tmpfile\n";
                exit(1); # this is fatal
              }
            }

            # move temp file to source file
            $c = 'mv '.escapeshellarg($tmpfile).' '.escapeshellarg($srcfile);

            unset($o, $r1);
            exec($c, $o, $r1);
            if ($r1 !== 0) {
              echo get_line_clear($header);
              echo "* mv command $c failed: ".implode(" ", $o)."\n";
              echo "Temporary file is left at $tmpfile\n";
              $stats['failed']++;
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_EXEC_MV_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              exit(1); # this is fatal
            }

            # md5 check II
            $md5 = md5_file($srcfile);
            if ($md5 === false) {
              echo get_line_clear($header);
              echo "* MD5 hash failed after second move, file: $srcfile ($srcfile)\n";
              if ($config_backup_original_before_rewrite) {
                echo "  Original file is left at $origfile\n";
              }
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_MD5_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            if (strlen($row['md5']) && $md5 != $row['md5']) {
              echo get_line_clear($header);
              echo "* MD5 mismatches after second move, $md5 vs ".$row['md5'].", file: $srcfile\n";
              if ($config_backup_original_before_rewrite) {
                echo "  Original file is left at $origfile\n";
              }
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_REWRITE_MD5_MISMATCH).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            if ($config_backup_original_before_rewrite && file_exists($origfile) && !unlink($origfile)) {
              echo get_line_clear($header);
              echo "* Failed removing temporary original file: $origfile\n";
              if (!$db->query('UPDATE files SET status = "'.mres(STATUS_ERROR_UNLINK_ORIGINAL_FILE_FAILED).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"')) exit(1);
              $currentstatus = 'FAILED';
              $stats['failed']++;
              continue;
            }

            $db->query('UPDATE files SET rewrites=rewrites+1, rewritten="'.date('Y-m-d H:i:s').'", status = "'.mres(STATUS_REWRITTEN).'", updated = "'.mresnow().'" WHERE id="'.mres($row['id']).'"');
            $currentstatus = 'Rewrote';
            $stats['rewritten']++;

            $logmessage_range = get_logmessage_range($logmessage_range, $row['id']);

            if (!$db->query('UPDATE log SET
                updated = "'.mresnow().'",
                ids_files="'.mres(get_formatted_logmessage_range($logmessage_range)).'"
              WHERE id="'.mres($id_logs).'"')) exit(1);

            echo get_line_clear($header);
            $header = getheader($i, $total, $stats, $currentstatus.' '.$srcfile."\r");
            echo $header;
          }
          echo get_line_clear($header);
          echo getheader($i, $total, $stats, 'Rewrote '.$stats['rewritten'].' files, '.$stats['missing'].' missing, '.$stats['failed'].' failed'."\r")."\n";
          break;
      } # re-switch
      break;
    case 's': # stats
      $db = get_db_conn();
      $dbpath = get_db_path();
      echo 'Database: '.$dbpath.' ('.get_si_size(filesize($dbpath)).")\n";
      echo 'Free space, database and tree: '.get_si_size(disk_free_space(dirname($dbpath)))."\n";
      if ($config_tmpdir != false) {
        echo 'Free space, temporary directory: '.get_si_size(disk_free_space(dirname($config_tmpdir)))."\n";
      }
      echo "Files and statuses:\n";
      $r = $db->query('SELECT status, COUNT(id) AS quantity FROM files GROUP BY status ORDER BY status');
      $w = 0;
      $result = array();
      while ($row = $r->fetchArray()) {
        $result[] = $row;
        $w = strlen($row['quantity']) > $w ? strlen($row['quantity']) : $w;
      }

      foreach ($result as $row) {
        echo ' * '.str_pad($row['quantity'], $w, ' ', STR_PAD_LEFT).' '.lcfirst(array_key_exists($row['status'], $statuses) ? $statuses[$row['status']] : 'Unknown status '.$row['status'])."\n";
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
