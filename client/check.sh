#!/bin/bash

DIRECTORY=`dirname "$0"`

if [ ! -e /etc/ddns.conf ]; then
  echo "/etc/ddns.conf does not exist"
  exit 1
fi

HOSTNAME=`grep DDNS_HOSTNAME /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$HOSTNAME" == "" ]; then
  echo "/etc/ddns.conf not configured properly"
  exit 1
fi
DOMAIN=`grep DDNS_DOMAIN /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$DOMAIN" == "" ]; then
  echo "/etc/ddns.conf not configured properly"
  exit 1
fi
NAMESERVER=`grep DDNS_NAMESERVER /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$NAMESERVER" == "" ]; then
  echo "/etc/ddns.conf not configured properly"
  exit 1
fi
ENDPOINT=`grep DDNS_ENDPOINT /etc/ddns.conf 2>/dev/null | sed -e 's/^.*=//'`
if [ "$ENDPOINT" == "" ]; then
  echo "/etc/ddns.conf not configured properly"
  exit 1
fi


CURRENT=`dig +short -t A "$HOSTNAME.$DOMAIN" "@$NAMESERVER"`
WHITESPACES=`echo "$CURRENT" | grep -c ' '`
if [ "$WHITESPACES" -ne "0" ]; then
  echo "Multiple IPv4 addresses found via nameserver"
  exit 1
fi

EXPECTED=`wget -4 -q -O - $ENDPOINT/ip.php`
if [ "$EXPECTED" == "" ]; then
  echo "Unable to determine public IPv4 address"
  exit 1
elif [ "$CURRENT" != "$EXPECTED" ]; then
  echo "Update of IPv4 address required"
  reason=MANUAL bash "$DIRECTORY/dhcpcd-hook"
fi


CURRENT=`dig +short -t AAAA "$HOSTNAME.$DOMAIN" "@$NAMESERVER"`
WHITESPACES=`echo "$CURRENT" | grep -c ' '`
if [ "$WHITESPACES" -ne "0" ]; then
  echo "Multiple IPv6 addresses found via nameserver"
  exit 1
fi

EXPECTED=`wget -6 -q -O - $ENDPOINT/ip.php`
if [ "$EXPECTED" == "" ]; then
  echo "Unable to determine public IPv6 address"
  exit 1
elif [ "$CURRENT" != "$EXPECTED" ]; then
  echo "Update of IPv6 address required"
  reason=MANUAL6 bash "$DIRECTORY/dhcpcd-hook"
fi

