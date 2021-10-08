# Warehouse

This repo is for https://warehouse.courseassembler.com/

Users can store their packages and themes in a folder underneath /public/data/(hash-key)/ for as long as they hold a licence.

There is currently nothing deleting old expired licences.

Config expects to verify the hash and origin of the incoming data. Variables are set at the apache2/server environment level - though in this case they are in `/etc/apache2/conf-enabled/vars.conf`

