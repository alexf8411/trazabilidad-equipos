/**
 * URTRACK - Reportes JavaScript
 * Versión 3.1 - SQL Server
 * 
 * Manejo de gráficas Chart.js para el módulo de reportes
 */

// Esperar a que se cargue el DOM y los datos
document.addEventListener('DOMContentLoaded', function() {
    
    // Verificar que existan los datos
    if (typeof window.reportesData === 'undefined') {
        console.error('Error: No se encontraron datos de reportes');
        return;
    }
    
    const datos = window.reportesData;
    
    // Configuración global de Chart.js
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#666';
    
    // ========================================================================
    // GRÁFICA 1: MODALIDAD (Pie Chart)
    // ========================================================================
    const ctxModalidad = document.getElementById('chartModalidad');
    if (ctxModalidad) {
        new Chart(ctxModalidad, {
            type: 'pie',
            data: {
                labels: datos.mod_labels,
                datasets: [{
                    data: datos.mod_data,
                    backgroundColor: [
                        '#002D72',  // Azul principal
                        '#28a745',  // Verde
                        '#ffc107',  // Amarillo
                        '#17a2b8',  // Azul claro
                        '#6f42c1',  // Morado
                        '#fd7e14'   // Naranja
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ========================================================================
    // GRÁFICA 2: SEDES (Bar Chart Horizontal)
    // ========================================================================
    const ctxSedes = document.getElementById('chartSedes');
    if (ctxSedes) {
        new Chart(ctxSedes, {
            type: 'bar',
            data: {
                labels: datos.sede_labels,
                datasets: [{
                    label: 'Equipos',
                    data: datos.sede_data,
                    backgroundColor: '#002D72',
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Equipos: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ========================================================================
    // GRÁFICA 3: TOP TÉCNICOS (Bar Chart)
    // ========================================================================
    const ctxTecnicos = document.getElementById('chartTecnicos');
    if (ctxTecnicos) {
        new Chart(ctxTecnicos, {
            type: 'bar',
            data: {
                labels: datos.tec_labels,
                datasets: [{
                    label: 'Movimientos',
                    data: datos.tec_data,
                    backgroundColor: '#17a2b8',
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Movimientos: ${context.parsed.x}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ========================================================================
    // GRÁFICA 4: CICLO DE VIDA (Doughnut Chart)
    // ========================================================================
    const ctxVida = document.getElementById('chartVida');
    if (ctxVida) {
        new Chart(ctxVida, {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Bajas'],
                datasets: [{
                    data: [datos.total_activos, datos.total_bajas],
                    backgroundColor: [
                        '#28a745',  // Verde para activos
                        '#dc3545'   // Rojo para bajas
                    ],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }
    
    console.log('✅ Gráficas de reportes cargadas correctamente');
});