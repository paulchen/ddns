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

DEVICE=`ip -j -6 route | jq -r 'map(select(.dst == "default").dev) | flatten | .[]'`
if [ "$DEVICE" == "" ]; then
  echo "No default route for IPv6 found"
  exit 1
fi
WHITESPACES=`echo "$DEVICE" | grep -c ' '`
if [ "$WHITESPACES" -ne "0" ]; then
  echo "Multiple default routes found"
  exit 1
fi

CURRENT=`dig +short -t AAAA "$HOSTNAME.$DOMAIN" "@$NAMESERVER"`
EXPECTED=`ip -j -6 addr show "$DEVICE" scope global | jq -r 'map(.addr_info) | map(map(select(.family == "inet6").local)) | flatten | .[]'`

FOUND=0
for IP in $EXPECTED; do
  if [ "$IP" == "$CURRENT" ]; then
    FOUND=1
    break
  fi
done

if [ "$FOUND" -ne "1" ]; then
  echo "Update required"
  reason=MANUAL6 bash "$DIRECTORY/dhcpcd-hook"
fi

