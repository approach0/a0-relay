#!/bin/sh
p="test/"

curl -v -H "Content-Type: application/json" -d @"${p}clicks.json" "http://localhost:8080/click-relay.php"
