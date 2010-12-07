#!/bin/bash -ex
MGD_SCHEMA_PATH=/usr/share/midgard2/schema
find . -path '*/models/*.xml' | while read RELPATH
do
    FILENAME=`basename "$RELPATH"`
    FULLPATH=`realpath "$RELPATH"`
    COMPONENT=`echo "$RELPATH" | sed -r 's%.*/([^/]+)/models/.+%\1%g'`
    ln -sf "$FULLPATH" "$MGD_SCHEMA_PATH/""$COMPONENT""_$FILENAME"
done
