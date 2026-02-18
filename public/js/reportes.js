/**
 * URTRACK - Reportes JavaScript
 * Versión 3.2 - SQL Server - CICLO DE VIDA CORREGIDO
 * 
 * Manejo de gráficas Chart.js para el módulo de reportes
 * 
 * CORRECCIONES:
 * ✅ Parseo de números en mod_data (venían como strings)
 * ✅ Verificación de datos antes de crear gráficas
 * ✅ Console logs para debugging
 */

// Esperar a que se cargue el DOM y los datos
document.addEventListener('DOMContentLoaded', function() {
    
    // Verificar que existan los datos
    if (typeof window.reportesData === 'undefined') {
        console.error('Error: No se encontraron datos de reportes');
        return;
    }
    
    const datos = window.reportesData;
    
    // 🔧 DEBUG: Verificar datos recibidos
    console.log('📊 Datos de reportes recibidos:', datos);
    console.log('✅ Total Activos:', datos.total_activos);
    console.log('✅ Total Bajas:', datos.total_bajas);
    
    // Configuración global de Chart.js
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#666';
    
    // ========================================================================
    // GRÁFICA 1: MODALIDAD (Pie Chart)
    // ========================================================================
    const ctxModalidad = document.getElementById('chartModalidad');
    if (ctxModalidad) {
        // 🔧 Convertir strings a números si es necesario
        const modData = datos.mod_data.map(v => parseInt(v) || 0);
        
        console.log('📊 Modalidad - Labels:', datos.mod_labels);
        console.log('📊 Modalidad - Data (original):', datos.mod_data);
        console.log('📊 Modalidad - Data (parseada):', modData);
        
        new Chart(ctxModalidad, {
            type: 'pie',
            data: {
                labels: datos.mod_labels,
                datasets: [{
                    data: modData,
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
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
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
        // 🔧 Convertir strings a números
        const sedeData = datos.sede_data.map(v => parseInt(v) || 0);
        
        console.log('📊 Sedes - Data (parseada):', sedeData);
        
        new Chart(ctxSedes, {
            type: 'bar',
            data: {
                labels: datos.sede_labels,
                datasets: [{
                    label: 'Equipos',
                    data: sedeData,
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
        // 🔧 Convertir strings a números
        const tecData = datos.tec_data.map(v => parseInt(v) || 0);
        
        console.log('📊 Técnicos - Data (parseada):', tecData);
        
        new Chart(ctxTecnicos, {
            type: 'bar',
            data: {
                labels: datos.tec_labels,
                datasets: [{
                    label: 'Movimientos',
                    data: tecData,
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
    // GRÁFICA 4: CICLO DE VIDA (Doughnut Chart) - 🔧 CORREGIDO
    // ========================================================================
    const ctxVida = document.getElementById('chartVida');
    if (ctxVida) {
        // 🔧 ASEGURAR QUE SON NÚMEROS (no strings)
        const totalActivos = parseInt(datos.total_activos) || 0;
        const totalBajas = parseInt(datos.total_bajas) || 0;
        
        console.log('📊 Ciclo de Vida - Activos:', totalActivos);
        console.log('📊 Ciclo de Vida - Bajas:', totalBajas);
        console.log('📊 Ciclo de Vida - Total:', totalActivos + totalBajas);
        
        // Validar que haya datos para mostrar
        if (totalActivos === 0 && totalBajas === 0) {
            console.warn('⚠️ No hay datos para mostrar en Ciclo de Vida');
            ctxVida.parentElement.innerHTML = '<p style="text-align:center; padding:40px; color:#999;">No hay datos disponibles</p>';
        } else {
            new Chart(ctxVida, {
                type: 'doughnut',
                data: {
                    labels: ['Activos', 'Bajas'],
                    datasets: [{
                        data: [totalActivos, totalBajas],
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
                                },
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            
                                            return {
                                                text: `${label}: ${value} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
            
            console.log('✅ Gráfica de Ciclo de Vida creada exitosamente');
        }
    }
    
    console.log('✅ Todas las gráficas de reportes cargadas correctamente');
});