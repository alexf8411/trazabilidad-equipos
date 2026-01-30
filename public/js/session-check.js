/**
 * public/js/session-check.js
 * Vigilante de Sesión: Verifica con el servidor si el usuario sigue activo.
 * Si el servidor dice "inactive" (por logout o timeout), expulsa al usuario.
 */

function verificarSesion() {
    // Petición asíncrona al backend
    fetch('status.php')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'active') {
                console.warn('Sesión inactiva detectada. Redirigiendo al login...');
                window.location.href = 'login.php';
            }
        })
        .catch(error => {
            console.error('Error verificando sesión:', error);
            // Opcional: Si falla la red, ¿qué hacemos? 
            // Por seguridad, podríamos redirigir también, o reintentar.
        });
}

// Se ejecuta cada vez que la página se muestra (carga normal o botón Atrás/Caché)
window.addEventListener('pageshow', function(event) {
    verificarSesion();
});

// Opcional: Verificar periódicamente cada 5 minutos por si la sesión caduca sola
setInterval(verificarSesion, 300000);