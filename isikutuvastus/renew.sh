#!/bin/sh
###################################################
# Kasutamine: renew.sh [ cron_interval_sec ]
#	Default: cron_interval_sec = 43200
#
# Käsufail tühistusnimekirjade uuendamiseks.
# 	- Kontrollib crl-dest uuendamise aega
#	- Võrdleb crontabi käivitamise intervalle ja tõmbab uued nimekirjad, kui vaja
#
# 
# Defineerimist vajavad muutujad:
#	CRL_PATH - Täielik tee tühistusnimekirjade kataloogini
#	COMMANDFILE - apache startup script (võib ka otse ära defineerida muutuja PROC, 
#				mis peaks olema siis täielik tee apache programmifailini)
#	ERROR_TO - e-posti aadress kuhu saata jama korral teade
#	
# NB! Üle tuleks vaadata ka kõik programmide asukohad, mis on defineeritud kohe selle
#	faili alguses.
#	
# Muidu abiks muutujad (sulgudes vaikimisi väärtused):
#	BEFORE (1800) - kui palju sekundeid enne jargmist crli uuedamist peaks hakkama 
#			crli tirimist alustama
#	DEBUG (yes) - info standardväljundisse mida hetkel tehakse
#	MAIL_SUBJECT (-s 'ESTEID CRL Update Error') - vea e-posti kirja pealkiri
#	CRON_INTERVAL (43200) - cronist selle faili kaivitamise inrervall sekundites
#				(saab ka kasurealt ette anda esimese parameetrina)
# Tesititud:
#	Redhat-7.3 (ise ehitatud apache-ga)
#	Redhat-8.0 (ise ehitatud apache-ga)
#	
#
# Autor: Reigo Küngas <reigo@cvotech.com>
# (C) Copyright 2003 Reigo Küngas; Cvo Technologies
#
# This shell script is released under the terms of the GNU General
# Public License Version 2, June 1991.  If you do not have a copy
# of the GNU General Public License Version 2, then one may be
# retrieved from http://www.reigo.net/GPL.html
#
####################################################


### UTILIITIDE ASUKOHAD ####
OPENSSL="/usr/bin/openssl";
CUT="/usr/bin/cut";
CAT="/bin/cat";
DATE="/bin/date";
WGET="/usr/bin/wget";
LN="/bin/ln";
MV="/bin/mv";
RM="/bin/rm";
SLEEP="/bin/sleep";
MAIL="/bin/mail";
PIDOF="/sbin/pidof";
KILLALL="/usr/bin/killall";
EXPR="/usr/bin/expr";

#### KUHU PANNA crl-id #####
CRL_PATH="/usr/local/apache2/idcard";

#### APACHE ENDA STARTUP SCRIPT ####
COMMANDFILE="/usr/local/apache2/bin/apachectl";

NEXTUPDATE="${OPENSSL} crl -nextupdate -noout";
PROC=`${CAT} $COMMANDFILE |grep "HTTPD="|cut -f2 -d"="|cut -f2 -d"'"`;

#### KELLELE SAADAME ERRORID
ERROR_TO="my@mydomain.com";
MAIL_SUBJECT="-s 'ESTEID CRL Update Error'";

#### 30 minti enne jargmist update-i
BEFORE=1800;

### eeldame by default, et script pannakse 2 korda paevas kaima
CRON_INTERVAL=43200;

### DEBUG
DEBUG="yes";

### PARAMI PUHUL EELDAME ET ANTAKSE CRONI INTERVALL SEKUNDITES
if test $# -gt 0
then
	CRON_INTERVAL=$1;
fi

### MILLAL SCRIPT ALUSTAS
START=`${DATE} +"%s"`;

### MILLAL JARGMINE KORD CRON SCRIPTI KAIVITAB
NEXT_RUN=`${EXPR} $START + $CRON_INTERVAL`;


### DEBUGI OUTPUT
output()
{
	if test -n "${DEBUG}"
	then
		echo `${DATE} +"%T"` "$*";
	fi
}

### ERRORI SAATMINE
error()
{
	echo "$*";
	if test -n "${ERROR_TO}"
	then
		echo "$*"|${MAIL} ${MAIL_SUBJECT} "${ERROR_TO}";
	fi
}

#### SHUT DOWN APACHE ####
stop_apache()
{
	is_running=`${PIDOF} $PROC`;
	while test -n "$is_running"
	do
		${KILLALL} $PROC 1>/dev/null 2>/dev/null
		${SLEEP} 1;
		is_running=`${PIDOF} $PROC`;
	done
	start_apache;
}


#### START APACHE ####
start_apache()
{
	#### BRING APACHE UP ####
	count=0;
	while  ! $PROC 
	do
		${SLEEP} 1;
		count=`${EXPR} $count + 1`;
		if test $count -gt 10
		then
			error "Apachet ei saa startida:( FILE=$COMMANDFILE;PROC=$PROC";
			exit;
		fi
	done
	check_apache;
}

#### CHECK APACHE #####
check_apache()
{
	is_running=`${PIDOF} $PROC`;
	if test -z "$is_running"
	then
		stop_apache;
	fi
}

### GENEREERIME APACHE JAOKS SYMLINGID
hash_symlinks()
{
	${RM} -f *.r0;
	for file in `ls *.crl`
	do
		symlink="`${OPENSSL} crl -hash -noout -in ${file}`.r0";
		if ! test -e "${symlink}"
		then
			${LN} -s "${file}" "${symlink}"
		fi
	done
}

### APACHE RESTART
restart_apache()
{
	if test -n "${RESTART_APACHE}"
	then
		hash_symlinks;

		output "Restarting Apache...";
		stop_apache;
		RESTART_APACHE="";
	fi
}

### TOMBAME CRLI
get()
{
	url=$1
	file=`echo $url|awk -F / '{print $(NF)}'`

	if test -z "$file"
	then
		error "Error: empty file (url=$url)";
		file="index.crl"
	fi

	### teeme failist backupi
	if test -s "$file"
	then
		${MV} -f "${file}" "${file}.bu"
	fi

	while ! test -s "$file"
	do
		output "Geting $url -> $file"
		wget -q "$url" -O "$file"
	done

	${OPENSSL} crl -in "${file}" -out "${file}" -inform DER

	RESTART_APACHE="yes";
}


### KONTROLLIME KAS CRL VAJAB UUENDAMIST JA MILLAL
check()
{
	url=$1
	file=`echo $url|awk -F / '{print $(NF)}'`

	if test -z "$file"
	then
		output "Error: empty file (url=$url)";
		return;
	fi

	if test -s "$file"
	then
		nexttime=`${NEXTUPDATE} -in $file|${CUT} -f2 -d=`;
		next_sec=`${DATE} +"%s" -d"${nexttime}"`;
		need_update=`${EXPR} $next_sec - ${BEFORE}`;

		now=`${DATE} +"%s"`;

		if test ${NEXT_RUN} -lt $need_update
		then
			return;
		fi

		if test $need_update -le $now
		then
			if test $next_sec -lt $now
			then
				output "NB Expired: $file";
			fi
			get "$url";
		else
			MY_SLEEP=`${EXPR} $need_update - $now`;
			if test -z "$DO_SLEEP" || $DO_SLEEP -gt $MY_SLEEP
			then
				DO_SLEEP=$MY_SLEEP;
				return;
			fi
		fi
	else
		output "NB! File does not exists: $file";
		get "$url";
	fi

	
	if ! test -s "$file"
	then
		error "NB! File still does not exists: $file";
		get "$url";
	else
		nexttime=`${NEXTUPDATE} -in $file|${CUT} -f2 -d=`;
		next_sec=`${DATE} +"%s" -d"${nexttime}"`;
		need_update=`${EXPR} $next_sec - ${BEFORE}`;
		
		##
		# kontrollime igaks petteks jargmist croni aega
		#
		if test ${NEXT_RUN} -gt $need_update
		then
			output "NB! CRL expires before next run";
			now=`${DATE} +"%s"`;
			MY_SLEEP=`${EXPR} $need_update - $now`;
			if test -z "$DO_SLEEP" || $DO_SLEEP -gt $MY_SLEEP
			then
				DO_SLEEP=$MY_SLEEP;
				return;
			fi
		fi
		
	fi
}

### TEEME SIIS MIDA VAJA TEHA
run()
{
	cd ${CRL_PATH};
	DO="yes";
	while test -n "$DO"
	do
		output "Checking certs";
		check "http://www.sk.ee/crls/esteid/esteid.crl"
		check "http://www.sk.ee/crls/juur/crl.crl"

		restart_apache;

		if test -n "$DO_SLEEP"
		then
			output "Sleep ${DO_SLEEP}";
			${SLEEP} "${DO_SLEEP}"
			DO_SLEEP="";
		else
			DO="";
		fi
	done
	cd -;
}


run;
exit;
