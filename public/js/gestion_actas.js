/**
 * public/js/gestion_actas.js
 * Manejo dinámico de envío y reenvío de actas URTRACK
 */

function enviarCorreo(serial, correo) {
    const btn = document.getElementById('btnSend');
    const btnText = document.getElementById('btnText');
    const msg = document.getElementById('statusMsg');
    
    // Detectar si es reenvío por el texto actual del botón
    const esReenvio = btnText.innerText.includes('Reenviar');
    const mensajeConfirm = esReenvio 
        ? `¿Desea volver a enviar el acta a ${correo}?` 
        : `¿Enviar acta oficial a ${correo}?`;

    if(!confirm(mensajeConfirm)) return;

    // Estado de carga
    btn.disabled = true;
    const originalContent = btnText.innerHTML;
    btnText.innerHTML = '⏳ Enviando...';
    msg.innerHTML = '';

    // Llamada al backend
    fetch(`generar_acta.php?serial=${serial}&action=send_mail`)
        .then(response => {
            return response.text().then(text => {
                if (response.ok && text.includes("OK")) return text;
                throw new Error(text || "Error desconocido en el servidor");
            });
        })
        .then(() => {
            // ÉXITO: Transformar botón a modo REENVIAR
            btn.disabled = false;
            btn.classList.add('btn-resend'); // Clase CSS para cambio de color
            btnText.innerHTML = 'Reenviar Acta';
            
            msg.innerHTML = '✅ Enviado con éxito.';
            msg.style.color = '#4ade80';
        })
        .catch(error => {
            btn.disabled = false;
            btnText.innerHTML = '❌ Reintentar';
            alert("Error: " + error.message);
            console.error("Detalle técnico:", error);
        });
}