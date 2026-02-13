/**
 * public/js/alta_equipos.js
 * Script de apoyo para el formulario de Registro Maestro
 */

document.addEventListener("DOMContentLoaded", function() {
    // 1. Convertir Serial y Placa a mayúsculas automáticamente para evitar errores de tipeo
    const serialInput = document.querySelector('input[name="serial"]');
    const placaInput = document.querySelector('input[name="placa"]');

    if (serialInput) {
        serialInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    if (placaInput) {
        placaInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // 2. Ocultar las notificaciones tipo "toast" (éxito) después de 5 segundos
    const toastSuccess = document.querySelector('.toast.success');
    if (toastSuccess) {
        setTimeout(() => {
            toastSuccess.style.transition = "opacity 0.5s ease";
            toastSuccess.style.opacity = "0";
            setTimeout(() => toastSuccess.remove(), 500);
        }, 5000);
    }
});