#!/bin/bash

# 2-57/5 * * * * <user> /opt/ddns/client/check.sh >> /var/log/ddns.log 2>&1

DIRECTORY=`dirname "$0"`

log() {
  date=`date +"%Y-%m-%d %H:%M:%S"`
  echo "$date - $1"
}

log "Executing DDNS check script"

if [ ! -e /etc/ddns.conf ]; then
  log "/etc/ddns.conf does not exist"
  exit 1
fi

HOSTNAME=`grep DDNS_HOSTNAME /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$HOSTNAME" == "" ]; then
  log "/etc/ddns.conf not configured properly"
  exit 1
fi
DOMAIN=`grep DDNS_DOMAIN /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$DOMAIN" == "" ]; then
  log "/etc/ddns.conf not configured properly"
  exit 1
fi
NAMESERVER=`grep DDNS_NAMESERVER /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$NAMESERVER" == "" ]; then
  log "/etc/ddns.conf not configured properly"
  exit 1
fi
ENDPOINT=`grep DDNS_ENDPOINT /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$ENDPOINT" == "" ]; then
  log "/etc/ddns.conf not configured properly"
  exit 1
fi

log "/etc/ddns.conf seems to be set up correctly"

ERROR=0

CURRENT=`dig +short -t A "$HOSTNAME.$DOMAIN" "@$NAMESERVER"`
WHITESPACES=`echo "$CURRENT" | grep -c ' '`
if [ "$WHITESPACES" -ne "0" ]; then
  log "Multiple IPv4 addresses found via nameserver"
  exit 1
fi

EXPECTED=`wget -4 -q -O - $ENDPOINT/ip.php`
if [ "$EXPECTED" == "" ]; then
  log "Unable to determine public IPv4 address"
  exit 1
elif [ "$CURRENT" != "$EXPECTED" ]; then
  log "Update of IPv4 address required"
  reason=MANUAL bash "$DIRECTORY/dhcpcd-hook" || ERROR=1
fi

if [ "$ERROR" -ne "0" ]; then
  log "IPv4 update failed"
  exit 1
fi

CURRENT=`dig +short -t AAAA "$HOSTNAME.$DOMAIN" "@$NAMESERVER"`
WHITESPACES=`echo "$CURRENT" | grep -c ' '`
if [ "$WHITESPACES" -ne "0" ]; then
  log "Multiple IPv6 addresses found via nameserver"
  exit 1
fi

EXPECTED=`wget -6 -q -O - $ENDPOINT/ip.php`
if [ "$EXPECTED" == "" ]; then
  log "Unable to determine public IPv6 address"
  exit 1
elif [ "$CURRENT" != "$EXPECTED" ]; then
  log "Update of IPv6 address required"
  reason=MANUAL6 bash "$DIRECTORY/dhcpcd-hook" || ERROR=1
fi

if [ "$ERROR" -ne "0" ]; then
  log "IPv6 update failed"
  exit 1
fi

log "Execution of DDNS check script completed"

# vim:ts=2:sw=2:expandtab


