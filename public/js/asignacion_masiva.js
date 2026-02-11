/**
 * public/js/asignacion_masiva.js
 * Lógica de filtrado de lugares y manejo de UI
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Elementos del DOM
    const selectSede = document.getElementById('selectSede');
    const selectLugar = document.getElementById('selectLugar');
    const btnSubmit = document.getElementById('btnSubmit');

    // 1. Evento para filtrar lugares según la sede
    if (selectSede) {
        selectSede.addEventListener('change', function() {
            const sedeSeleccionada = this.value;
            
            // Limpiar select de lugares
            selectLugar.innerHTML = '<option value="">-- Seleccionar --</option>';
            
            if (sedeSeleccionada === "") {
                selectLugar.disabled = true;
                return;
            }

            // Filtrar usando la variable global inyectada desde PHP (URTRACK_LUGARES)
            const lugaresFiltrados = URTRACK_LUGARES.filter(l => l.sede === sedeSeleccionada);
            
            lugaresFiltrados.forEach(l => {
                const option = document.createElement('option');
                option.value = l.id;
                option.textContent = l.nombre;
                selectLugar.appendChild(option);
            });

            selectLugar.disabled = false;
        });
    }

    // 2. Validación visual simple para habilitar botón
    // (Nota: La validación fuerte de LDAP ya la hacen los otros scripts incluidos)
    const inputsRequeridos = [selectSede, selectLugar, document.getElementById('correo_resp_real')];
    
    function checkForm() {
        if (!btnSubmit) return;
        
        // Verificamos si los campos básicos están llenos
        // La validación real ocurre al hacer submit, esto es solo UX visual
        const allFilled = inputsRequeridos.every(input => input && input.value !== '');
        
        if (allFilled && !btnSubmit.disabled) {
            btnSubmit.style.opacity = "1";
        }
    }

    inputsRequeridos.forEach(input => {
        if(input) input.addEventListener('change', checkForm);
    });
});