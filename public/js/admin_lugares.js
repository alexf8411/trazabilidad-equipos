/**
 * URTRACK - Admin Lugares JavaScript
 * Versión 2.1 - SQL Server
 * 
 * Filtrado de tabla de ubicaciones
 */

/**
 * Filtrar tabla de lugares por sede o nombre
 */
function filterTable() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toUpperCase();
    const table = document.getElementById("lugaresTable");
    const tr = table.getElementsByTagName("tr");

    // Iterar sobre todas las filas (excepto el encabezado)
    for (let i = 1; i < tr.length; i++) {
        const tdSede = tr[i].getElementsByTagName("td")[0];
        const tdNombre = tr[i].getElementsByTagName("td")[1];
        
        if (tdSede || tdNombre) {
            // Combinar texto de sede y nombre
            const txtValue = (tdSede.textContent || tdSede.innerText) + " " + 
                            (tdNombre.textContent || tdNombre.innerText);
            
            // Mostrar/ocultar fila según coincidencia
            tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }
}

console.log('✅ Módulo de gestión de lugares cargado correctamente');