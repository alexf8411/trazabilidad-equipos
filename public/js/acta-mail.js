/**
 * public/js/acta-mail.js
 * Maneja el envío de correos de actas y actualiza la UI
 */

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnSend');
    
    // Verificamos que el botón exista antes de agregar el listener
    if (btn) {
        btn.addEventListener('click', enviarCorreo);
    }
});

function enviarCorreo() {
    const btn = document.getElementById('btnSend');
    const msg = document.getElementById('statusMsg');
    
    // Obtenemos los datos desde los atributos data- del botón HTML
    // Esto desacopla el PHP del JS
    const serial = btn.getAttribute('data-serial');
    const email = btn.getAttribute('data-email');
    const placa = btn.getAttribute('data-placa');

    if(!confirm(`¿Desea enviar (o reenviar) el acta del activo ${placa} a ${email}?`)) return;

    // Estado 1: Cargando
    btn.disabled = true;
    btn.innerHTML = '⏳ Enviando...';
    msg.innerHTML = '';

    // Petición AJAX
    fetch(`generar_acta.php?serial=${serial}&action=send_mail`)
        .then(response => {
            if (response.ok) return response.text();
            throw new Error('Error en la respuesta del servidor');
        })
        .then(() => {
            // Estado 2: Éxito -> Cambiar texto a REENVIAR y habilitar botón
            btn.innerHTML = '🔄 Reenviar Acta'; // Aquí está el cambio solicitado
            btn.disabled = false; // Habilitamos de nuevo para permitir reenvío
            
            // Feedback visual
            msg.innerHTML = '✅ Correo entregado correctamente.';
            msg.style.color = '#4ade80';

            // Opcional: Ocultar el mensaje de éxito después de 5 segundos
            setTimeout(() => {
                msg.innerHTML = '';
            }, 5000);
        })
        .catch(error => {
            // Estado 3: Error
            btn.disabled = false;
            btn.innerHTML = '❌ Reintentar';
            msg.innerHTML = 'Error de conexión o servidor.';
            msg.style.color = '#f87171';
            console.error(error);
            alert("Ocurrió un error al intentar enviar el correo.");
        });
}