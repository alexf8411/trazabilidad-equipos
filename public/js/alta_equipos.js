/**
 * public/js/alta_equipos.js
 * Script de funcionalidad para el formulario de Alta de Equipos
 * Mantiene la lógica original exacta
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================================================
    // AUTO-MAYÚSCULAS EN SERIAL Y PLACA
    // ========================================================================
    const serialInput = document.getElementById('serial');
    const placaInput = document.getElementById('placa');

    if (serialInput) {
        serialInput.addEventListener('input', function() {
            const cursorPos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(cursorPos, cursorPos);
        });
    }

    if (placaInput) {
        placaInput.addEventListener('input', function() {
            const cursorPos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(cursorPos, cursorPos);
        });
    }

    // ========================================================================
    // AUTO-CERRAR ALERTAS DE ÉXITO DESPUÉS DE 5 SEGUNDOS
    // ========================================================================
    const successToast = document.querySelector('.toast.success');
    
    if (successToast) {
        setTimeout(function() {
            successToast.style.transition = 'opacity 0.5s ease';
            successToast.style.opacity = '0';
            
            setTimeout(function() {
                successToast.remove();
            }, 500);
        }, 5000);
    }

    // ========================================================================
    // PREVENIR DOBLE ENVÍO DEL FORMULARIO
    // ========================================================================
    const form = document.getElementById('formAlta');
    
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Guardando...';
            }
        });
    }

    // ========================================================================
    // VALIDACIÓN ADICIONAL (OPCIONAL)
    // ========================================================================
    
    // Validar que vida útil esté entre 1 y 50
    const vidaUtilInput = document.querySelector('input[name="vida_util"]');
    
    if (vidaUtilInput) {
        vidaUtilInput.addEventListener('change', function() {
            const valor = parseInt(this.value);
            
            if (valor < 1) {
                this.value = 1;
                alert('La vida útil mínima es 1 año');
            } else if (valor > 50) {
                this.value = 50;
                alert('La vida útil máxima es 50 años');
            }
        });
    }

    // Validar que el precio sea mayor a cero
    const precioInput = document.querySelector('input[name="precio"]');
    
    if (precioInput) {
        precioInput.addEventListener('blur', function() {
            const valor = parseFloat(this.value);
            
            if (valor <= 0) {
                alert('El precio debe ser mayor a cero');
                this.focus();
            }
        });
    }
});