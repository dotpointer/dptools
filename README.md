# dptools

dptools is a variated collection of useful tools.

### backup-create, backup-mount, backup-sync, backup-umount

Create an encrypted remote backup over SSH, mount it, backup
to it and then unmount it. Made in Bash, configuration file
is `/etc/dptools/backuptools`.

### backup-databases

Backup MySQL/MariaDB databases and remove outdated backups.
Useful as a daily, weekly or monthly cronjob. Made in PHP, 
configuration file is `/etc/dptools/backup-databases`.

### batterywarning

Checks if the laptop charger is connected and if not then it warns.
Made in PHP.

### catconfig

Output the contents of a configuration file without # lines and empty 
lines. Made in PHP.

### clearexif

Clear EXIF data in (image) files. Made in PHP.

### datekeeper

Store and restore modify dates on files in a directory. Run it, edit the files
while it runs, and when done editing press a key to reset the modify dates.
Made in PHP.

### dhcp-script

Run actions upon dnsmasq leases. Made in PHP, configuration file is
`/etc/dptools/dhcp-script`.

### dotshaper

Simplified traffic shaping, based on wondershaper. Made in Bash.

### dp_console_setup.sh

Console formatter with a lot of aliases.

### dptools-update

Update dptools. Made in Bash.

### dynamichost-updater

DynDNS 2 updating client. Made in PHP, configuration is in 
`/etc/dptools/dynamichost-updater`.

### exifrenamer

Rename file using EXIF data artist and title. Made in PHP.

### fakecheck

Check if file contain anything else than null characters,
returns 0 if non-null.

### gettextrefresh

Update gettext translation PO files in multiple ways. Made in PHP.

### grepa

Recursively find files containing a case-sensitive text string. Made in Bash.

### igrepa

Recursively find files containing a case-insensitive text string. Made in Bash.

### jsonprint

Pretty-print JSON files. Made in PHP.

### jsonprintfix

Rewrite JSON files as pretty-printed. Made in PHP.

### killr

Kills processes matching a keyword. Kill -9 and killall in a combination. Made in PHP.

### machete

Remove files that are considered to not contain audio, image or video data.
Useful for mining data on disks with unknown content. Made in PHP.

### makenginxcert

Create nginx certificates. Made in Bash.

### makeutf8

Convert a file from ISO-8859-1 to UTF-8. Made in Bash.

### mp4tom4a

Convert a MP4 file to a M4a file. Made in Bash.

### pdfgreyscale

Convert a PDF file to greyscale. Made in PHP.

### pdfgreyscaleall

Convert all PDF files in a directory to greyscale recursively. Made in PHP.

### photos2

Move and resize photos from memory cards, useful for auctioning photos. Made in PHP.

### phpdircheck

PHP lint check a directory recursively and stop if any errors occour. Made in PHP.

### phpshorttagreplace

Replace PHP short tags (<?) with the longer recommended version (<?php). Made in PHP.

### publish

Validates and publishes content from an internal project directory to a public directory
by reading a `.dptools` configuration file in the project root folder. Made in PHP.

### sortbymodify

Sort files into date subdirectories, based on the modify time of the files. Made in PHP.

### soundrenamer

Rename audio files automatically. Made in PHP.

### stampresizer

Mangler for scanned stamp auction images. Resizes, brands and reorganizes the pictures
so they become ready to publish on auction sites.

### sync-dependencies

Ensures that dependency files in project directories are equal to the dependency source files.
Solves the problem with symbolically linked dependencies outside of the project directory
in Git by copying the dependencies into the project directory. Made in PHP, configuration
file is `/etc/dptools/sync-dependencies`.

### textcollector

Walk a directory for text files and then output all contents of them with path and last modified-date,
so it can be stored in one file, may also delete original file if requested. Made in PHP.

### transfer

Move files from one location to another using rsync, ftp or other services using cron.
Useful in cronjobs. Made in PHP, configuration file is `/etc/dptools/transfer`.

### update-flash

Flash updater for Chromium - downloads and extracts update. Made in PHP.

### videosheet

Video sheet generator. Creates an image file with thumbnails for a video file. Made in PHP.

### winmount

Mount a Samba share using CIFS in a user friendly way.

### vmaddusb

VirtualBox, add USB device. Made in Bash.

### vmcap

VirtualBox, get and set machine CPU cap. Made in Bash.

### vmcd

VirtualBox, set machine optical SATA device disc. Made in Bash.

### vmcdide

VirtualBox, set optical IDE device. Made in Bash.

### vmcompress

VirtualBox, disconnect, compact and reconnect all disks on a machine. Made in PHP.

### vmcpus

VirtualBox, get and set the number of machine CPU:s. Made in Bash.

### vmdel

VirtualBox, delete machine. Made in Bash.

### vmdiskmount

VirtualBox, mount machine IDE device. Made in Bash.

### vmfloppy

VirtualBox, insert and eject machine floppy disks. Made in Bash.

### vminfo

VirtualBox, show machine information. Made in Bash.

### vmmem

VirtualBox, set machine working memory. Made in Bash.

### vmmount

VirtualBox, mount IDE device. Made in Bash.

### vmnew

VirtualBox, create a new machine. Made in Bash.

### vmoff

VirtualBox, turn a machine off. Made in Bash.

### vmrealcd, vmrealcdoff

VirtualBox, mount and unmount SATA optical drive. Made in Bash.

### vmremusb

VirtualBox, remove USB device from machine. Made in Bash.

### vmreset

VirtualBox, reset machine power state. Made in Bash.

### vmrestart

VirtualBox, restart a machine. Made in Bash.

### vmstart

VirtualBox, start a machine. Made in Bash.

### vmstop

VirtualBox, stop a machine. Made in Bash.

### vmusbclear

VirtualBox, clear machine USB devices. Made in Bash.

## Other commands - aliases

Please have a look of the commands defined as aliases in the `dp_console_setup.sh` file.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for usage, development and testing purposes.

### Prerequisites

The following is necessary to run the software:

```
- Debian Linux 9 or similar system
- Bash shell
- PHP
- PHP-cURL
- PHP-MySQLi
- Rsync
- Zenity
```

Some more things may be necessary for some of the tools.
Try to run them to find out what is missing.

Bash is usually installed by default.
The rest can be installed using apt-get:

```
sudo apt-get install php php-curl php-mysqli rsync zenity
```

### Installing

Go root
```
sudo bash
```

Clone the repository
```
git clone https://gitlab.com/dotpointer/mysql-shim.git /opt/dptools
```

Make all executable
```
chmod 755 /opt/dptools
chmod -x /opt/dptools/README
```

Open ~/.bashrc and ~/.profile for all users that you want to have
the dptools available for and put this line at the bottom of each of them:

```
. /opt/dptools/dp_console_setup.sh
```
