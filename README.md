Bixie Printshop Cronjobs
==================

Cronjobs voor Bixie Printshop

_Bestanden buiten webroot server!_

Dagelijkse complete backup:
```
01	5	*	*	*	/usr/local/bin/php /home/crmprescan/domains/virtueupload.org/cronjobs/cronjobs.php dumpdb -f daily	
```
Vijfmaal daags belangrijkste tabellen
```
01	8,12,15,18,23	*	*	*	/usr/local/bin/php /home/crmprescan/domains/virtueupload.org/cronjobs/cronjobs.php dumpdb -m 5 -t freq
```
