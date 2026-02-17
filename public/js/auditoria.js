/**
 * URTRACK - Auditoría JavaScript
 * Versión 2.1 - SQL Server
 * 
 * Manejo de tabs y exportación CSV para el módulo de auditoría
 */

/**
 * Cambiar entre pestañas
 */
function openTab(evt, tabName) {
    // Ocultar todos los contenidos
    const tabcontent = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    
    // Desactivar todos los botones
    const tablinks = document.getElementsByClassName("tab-btn");
    for (let i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    
    // Activar pestaña seleccionada
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

/**
 * Exportar tabla a CSV
 * @param {string} tipo - 'cambios' o 'accesos'
 */
function exportarCSV(tipo) {
    const tabla = tipo === 'cambios' ? 'tabla-cambios' : 'tabla-accesos';
    const csv = [];
    const rows = document.querySelectorAll('#' + tabla + ' tr');
    
    // Convertir tabla a array CSV
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Escapar comillas dobles
            let texto = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + texto + '"');
        }
        csv.push(row.join(','));
    }
    
    // Crear blob y descargar
    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const downloadLink = document.createElement('a');
    
    // Nombre del archivo con fecha
    const fecha = new Date().toISOString().slice(0,10);
    downloadLink.download = 'auditoria_' + tipo + '_' + fecha + '.csv';
    
    // Generar link y hacer click automático
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

console.log('✅ Módulo de auditoría cargado correctamente');