#!/bin/bash

# --- dp console setup - an /etc/profile.d/ script to fix the console by Robert Klebe

# changelog:
#	2006-xx-xx	first version
#	2012-12-20	cleaning up
#	2014-04-26     	adding automatic coloring based on hostname md5sum
#       2016-03-04	adding colors for less

# is TERM variable defined, ssh sets it to dump when using scp
if [[ -n "$TERM" && "$TERM" != "dumb" ]]; then
      # ubuntu 14.10 cannot suddenly take blength and bfreq, so we must
      # make it shut up even if it fails
      setterm -blength 0 > /dev/null 2>&1
      setterm -bfreq 0 > /dev/null 2>&1
fi


# path - note that /usr/sbin is included for normal users, otherwise it isn't
PATH="$PATH:/var/scripts:/var/scripts/dptools:/usr/sbin:/sbin"

# aliases
alias array='cat /proc/scsi/rr172x/0';
alias addgroup='groupadd';
alias backupdir='tar -cvzf';
alias cd..='cd ..';
alias cd...='cd ...';
alias chkdsk='dosfsck -v -a -w';
alias copy='cp -i';
alias cl='clear';
alias clea='clear';
alias clae='clear';
alias clr='clear';
alias cls='clear';
alias cp='cp --preserve';
# alias date='date +"%n DATE: %Y-%m-%d%n TIME: %H:%M:%S%n"';
alias del='rm -i';
alias delbackups='rm ./*~ 2> /dev/null; rm ./.*~ 2> /dev/null';
alias delete='rm -i';
alias dnsreload='killall dnsmasq -s SIGHUP';
alias df='df -h';
alias dir='ls -la';
alias dirsize='du -sh';
alias dirsizemore='du -h --max-depth=1';
alias diskinfo='df -h';
alias dmesg='dmesg --time-format=ctime';
alias dmesŋ='dmesg';
alias dos2unix='dos2unix -k'
alias edt='edit';
alias edit='jed';
alias filldisk='yes abcdefghijklmnopqrstuvwxyz0123456789 > diskfiller';
alias glance='glances';
alias iostat='iostat -m';
alias ipcfg='ifconfig';
alias ipconfig='ifconfig';
alias iptraffic='iptraf';
alias jsonlint='python -m json.tool <';
alias k='kill';
alias killall='killall -9';
alias kilall='killall';
alias lastignored='tail --lines 2000 /var/log/messages|grep dnsmasq|grep ignored';
alias lastips='tail --lines 2000 /var/log/messages|grep DHCPOFFER|tac';
alias less='less -R';
alias logoff='logout';
alias ls='ls --color --time-style=+%Y-%m\-%d\ %H\:%M\:%S';
alias ls-la='ls -la';
alias lynx='lynx -accept_all_cookies';
alias mmesg='cat /var/log/messages';
alias move='mv -i';
alias out='logout';
alias phpc='php -l';
alias phpcheck='php -l';
alias putty='ssh';
alias ppp-up='ppp-on';
alias ppp-down='ppp-stop';
alias ps='ps -e';
alias q='exit';
alias reboot='reboot';
alias reprofile='source ~/.profile';
alias restart='reboot';
alias smesg='cat /var/log/syslog';
alias tail='tail -n30';
# alias time='date +"%n DATE: %Y-%m-%d%n TIME: %H:%M:%S%n"';
alias tlist='ps';
alias tracert='traceroute';
alias type='cat';
alias unamsg='cat /var/log/unattended-upgrades/unattended-upgrades-dpkg.log';
alias unmount='umount';
alias updatedcs="if [ -e /tmp/tmp-dcs ]; then rm /tmp/tmp-dcs; fi; wget -O /tmp/tmp-dcs http://www.dotpointer.tk/service/?a=dcs && mv /tmp/tmp-dcs /var/scripts/dp_console_setup.sh && chmod 775 /var/scripts/dp_console_setup.sh && chown root:users /var/scripts/dp_console_setup.sh && reprofile"
alias volumedown="amixer -q -c0 set Master 3-%";
alias volumeup="amixer -q -c0 set Master 3+%";
alias vwdial="wvdial";
alias watch='watch -n1';
alias wget="wget --no-check-certificate";
# alias grepa2="grep --color -f ./ -rn";
# alias funk="function gd() { echo $1; } gd;";

# this will not work when xsession/mdm tries to read the config
# it does not understand functions
# as we cannot send in arguments using $1 in bash
# we do it as an exported function instead
# function grepa {
#	grep -rn "$1" --color ./
# }

# is there an md5sum binary in this system?
if [[ -f "/usr/bin/md5sum" && -x "/usr/bin/md5sum" ]]; then
	# get md5 of the hostname, here md5 should be checked for existence
	COLOR_MARKER_TEMP_NUMBER=$(hostname | md5sum);
	# cut out one character, convert it from hex to base 10, take second character
	COLOR_MARKER_TEMP_NUMBER=$((16#${COLOR_MARKER_TEMP_NUMBER:1:1}));	
else
	# fallback to red
	COLOR_MARKER_TEMP_NUMBER=13;
fi

# color constants for the prompt, beware, here are escaped special characters
COLOR_BLK="\[[0;30m\]"
COLOR_RED="\[[0;31m\]"
COLOR_GRE="\[[0;32m\]"
COLOR_YEL="\[[0;33m\]"
COLOR_BLU="\[[0;34m\]"
COLOR_MAG="\[[0;35m\]"
COLOR_CYA="\[[0;36m\]"
COLOR_BLD="\[[0;37m\]"
COLOR_WHT="\[[1;30m\]"
COLOR_HBK="\[[1;31m\]"
COLOR_HGR="\[[1;32m\]"
COLOR_HYE="\[[1;33m\]"
COLOR_HBL="\[[1;34m\]"
COLOR_HMA="\[[1;35m\]"
COLOR_HCY="\[[1;36m\]"
COLOR_HWH="\[[1;37m\]"

# translate selected marker color number to real color
case $COLOR_MARKER_TEMP_NUMBER in
	0 )
		COLOR_MARKER="$COLOR_WHT" # black shows nothing
		;;
	1 )
		COLOR_MARKER="$COLOR_BLD"
		;;
	2 )
		COLOR_MARKER="$COLOR_GRE"
		;;			   
	3 )
		COLOR_MARKER="$COLOR_YEL"
		;;
	4 )
		COLOR_MARKER="$COLOR_BLU"
		;;
	5 )
		COLOR_MARKER="$COLOR_MAG"
		;;
	6 )
		COLOR_MARKER="$COLOR_CYA"
		;;
	7 )
		COLOR_MARKER="$COLOR_RED"
		;;
	8 )
		COLOR_MARKER="$COLOR_WHT"
		;;		
	9 )
		COLOR_MARKER="$COLOR_HBK"
		;;		
	10 )
		COLOR_MARKER="$COLOR_HGR"
		;;		
	11 )
		COLOR_MARKER="$COLOR_HYE"
		;;
	12 )
		COLOR_MARKER="$COLOR_HBL"
		;;
	13 )
		COLOR_MARKER="$COLOR_HMA"
		;;
	14 )
		COLOR_MARKER="$COLOR_HCY"
		;;		
	15 )
		COLOR_MARKER="$COLOR_HWH"
		;;		
esac

# setup the prompt with colors
PS1="$COLOR_MARKER<$COLOR_HWH\t$COLOR_MARKER|$COLOR_HWH\u$COLOR_MARKER@$COLOR_HWH\h$COLOR_MARKER:$COLOR_HWH\w$COLOR_MARKER:$COLOR_HWH\$$COLOR_MARKER>$COLOR_HWH "
export PS1=$PS1

# is there a customization script to run too?
# -x for executable did not work when /var/scripts is a symlink
# so we try -e
if [ -e /var/scripts/dp_console_setup_customization.sh ]; then
	. /var/scripts/dp_console_setup_customization.sh
fi
