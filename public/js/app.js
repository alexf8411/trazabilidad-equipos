/**
 * URTRACK SYSTEM - CORE JAVASCRIPT V3.0
 * Funcionalidades comunes para el sistema de trazabilidad
 */

// ============================================================================
// UTILIDADES GENERALES
// ============================================================================

const URTrack = {
    /**
     * Muestra mensaje de confirmación antes de acciones críticas
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
            return true;
        }
        return false;
    },

    /**
     * Formatea números como moneda COP
     */
    formatCurrency: function(value) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0
        }).format(value);
    },

    /**
     * Formatea fechas al formato DD/MM/YYYY
     */
    formatDate: function(dateString) {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    },

    /**
     * Debounce para búsquedas (evita requests excesivos)
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Valida formato de email
     */
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
};

// ============================================================================
// BÚSQUEDA INSTANTÁNEA (Live Search)
// ============================================================================

function initLiveSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    const debouncedSearch = URTrack.debounce(function(e) {
        const form = e.target.closest('form');
        if (form && e.target.value.length >= 2) {
            // Auto-submit después de 500ms de inactividad
            // form.submit(); // Descomenta si quieres auto-submit
        }
    }, 500);

    searchInput.addEventListener('input', debouncedSearch);
}

// ============================================================================
// VALIDACIÓN DE FORMULARIOS
// ============================================================================

function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showFieldError(field, 'Este campo es obligatorio');
                } else {
                    field.classList.remove('error');
                    clearFieldError(field);
                }

                // Validación de email
                if (field.type === 'email' && field.value) {
                    if (!URTrack.validateEmail(field.value)) {
                        isValid = false;
                        field.classList.add('error');
                        showFieldError(field, 'Email inválido');
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios correctamente');
            }
        });

        // Limpiar errores al escribir
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('error');
                clearFieldError(this);
            });
        });
    });
}

function showFieldError(field, message) {
    clearFieldError(field);
    const error = document.createElement('small');
    error.className = 'field-error text-danger';
    error.textContent = message;
    error.style.display = 'block';
    error.style.marginTop = '4px';
    field.parentNode.appendChild(error);
}

function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// ============================================================================
// CONFIRMACIÓN DE ACCIONES CRÍTICAS
// ============================================================================

function initConfirmActions() {
    // Confirmar eliminaciones/bajas
    const deleteLinks = document.querySelectorAll('[data-confirm]');
    
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.dataset.confirm || '¿Está seguro de realizar esta acción?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// ============================================================================
// PREVIEW DE ARCHIVOS CSV
// ============================================================================

function initFilePreview() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Mostrar información del archivo
            const fileName = file.name;
            const fileSize = (file.size / 1024).toFixed(2) + ' KB';
            
            const preview = document.createElement('div');
            preview.className = 'file-preview alert alert-info mt-2';
            preview.innerHTML = `
                <strong>Archivo seleccionado:</strong><br>
                📄 ${fileName}<br>
                💾 Tamaño: ${fileSize}
            `;

            // Remover preview anterior si existe
            const existingPreview = this.parentNode.querySelector('.file-preview');
            if (existingPreview) {
                existingPreview.remove();
            }

            this.parentNode.appendChild(preview);
        });
    });
}

// ============================================================================
// TABLA RESPONSIVE - MEJORAS
// ============================================================================

function enhanceResponsiveTables() {
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('thead th');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index].textContent.trim());
                }
            });
        });
    });
}

// ============================================================================
// SCROLL SUAVE A ALERTAS
// ============================================================================

function scrollToAlerts() {
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        alerts[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ============================================================================
// AUTO-CLOSE DE ALERTAS
// ============================================================================

function initAutoCloseAlerts() {
    const successAlerts = document.querySelectorAll('.alert-success');
    
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000); // Se oculta después de 5 segundos
    });
}

// ============================================================================
// LOADING STATE EN BOTONES
// ============================================================================

function initLoadingButtons() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-small"></span> Procesando...';
            }
        });
    });
}

// ============================================================================
// TOOLTIPS SIMPLES
// ============================================================================

function initTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[title]');
    
    elementsWithTooltip.forEach(el => {
        el.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (!title) return;

            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = title;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 0.85rem;
                z-index: 1000;
                pointer-events: none;
                white-space: nowrap;
            `;

            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';

            this.dataset.tooltipId = Date.now();
            tooltip.dataset.tooltipId = this.dataset.tooltipId;
        });

        el.addEventListener('mouseleave', function() {
            const tooltipId = this.dataset.tooltipId;
            const tooltip = document.querySelector(`.custom-tooltip[data-tooltip-id="${tooltipId}"]`);
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
}

// ============================================================================
// INICIALIZACIÓN AL CARGAR LA PÁGINA
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todas las funcionalidades
    initLiveSearch();
    initFormValidation();
    initConfirmActions();
    initFilePreview();
    enhanceResponsiveTables();
    scrollToAlerts();
    initAutoCloseAlerts();
    initLoadingButtons();
    initTooltips();

    console.log('✅ URTRACK System cargado correctamente');
});

// ============================================================================
// EXPORTAR PARA USO GLOBAL
// ============================================================================

window.URTrack = URTrack;
