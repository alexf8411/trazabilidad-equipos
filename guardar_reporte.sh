#!/bin/bash
# 1. Entrar a la carpeta donde está Playwright
cd ~/trazabilidad-equipos/tests_playwright

# 2. Correr los tests
npx playwright test

# 3. Crear carpeta con fecha en el backup
FECHA=$(date +%Y-%m-%d_%H-%M)
DESTINO=~/backups_tests/login_playwright_$FECHA
mkdir -p $DESTINO

# 4. Copiar el reporte (solo si el test generó algo)
if [ -d "playwright-report" ]; then
    cp -r playwright-report/* $DESTINO
    echo "✅ Reporte guardado en: $DESTINO"
    echo "🚀 Para verlo usa: npx playwright show-report $DESTINO"
else
    echo "❌ Error: No se generó el reporte. Revisa si los tests fallaron antes de empezar."
fi
