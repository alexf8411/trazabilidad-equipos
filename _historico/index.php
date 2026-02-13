echo "<h1>Trazabilidad Equipos OK</h1><ul>";
foreach (glob("*.php") as $f) echo "<li><a href='$f'>$f</a></li>";
echo "</ul>";
