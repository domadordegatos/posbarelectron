; Instala el Microsoft Visual C++ Redistributable (x64) en silencio como
; parte de la instalación de EneSoft POS.
;
; Por qué: el PHP portable embebido (ver scripts/download-php.js) es un
; binario nativo compilado con el compilador de Visual Studio de Microsoft,
; y depende en tiempo de ejecución de las DLL de ese runtime (VCRUNTIME140.dll,
; MSVCP140.dll, etc.). Una máquina de desarrollo ya las tiene instaladas de
; antes por otros programas, lo cual esconde el problema — pero un Windows 11
; recién instalado puede no tenerlas, y en ese caso php.exe no arranca en
; absoluto: falla con el código de salida 0xC0000135 (STATUS_DLL_NOT_FOUND),
; que en la app se ve como "El servidor interno (PHP) se cerró
; inesperadamente. Código de salida: 3221225781".
;
; El instalador de Microsoft (vc_redist.x64.exe, descargado por
; scripts/download-vcredist.js y empaquetado como extraResource — ver
; electron-builder.yml) ya detecta solo si el runtime ya está instalado y no
; hace nada en ese caso, así que es seguro correrlo siempre sin verificar
; antes. Se corre en silencio y sin pedir reinicio; si por cualquier motivo
; fallara, no se aborta la instalación de EneSoft POS por esto — el aviso
; queda en el log del instalador para poder diagnosticarlo.
!macro customInstall
  DetailPrint "Instalando componentes de Microsoft Visual C++ (necesarios para el servidor interno)…"
  ExecWait '"$INSTDIR\resources\vcredist\vc_redist.x64.exe" /install /quiet /norestart' $0
  DetailPrint "Microsoft Visual C++ Redistributable: código de salida $0"
!macroend
