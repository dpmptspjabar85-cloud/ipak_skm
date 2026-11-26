<?php
$c = new mysqli('127.0.0.1', 'root', '');
$r = $c->query("SHOW DATABASES LIKE 'db_sicantik_backoffice'");
echo $r->num_rows ? 'ADA' : 'TIDAK ADA';
$c->close();
