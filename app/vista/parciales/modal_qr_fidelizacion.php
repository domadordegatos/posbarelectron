<!-- QR de fidelización, de un solo uso — el cliente lo escanea con su propio
     celular (registro/verificación/canje ocurre en el servidor, no aquí), o
     se le imprime en papel si no puede o no quiere hacerlo desde la pantalla.
     Incluir junto con qrcode.min.js en cualquier página que necesite generar
     el QR (ver pb.generarQrFidelizacion() / pb.imprimirQrFidelizacion()). -->
<div class="modal fade" id="modalQrFidelizacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-qr-code"></i> Escanea para sumar visita</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="qr_fidelizacion_contenedor" class="d-flex justify-content-center mb-2"></div>
                <p class="small text-muted mb-0" id="qr_fidelizacion_estado"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnImprimirQrFidelizacion" onclick="pb.imprimirQrFidelizacion()" disabled>
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>
<script src="/assets/vendor/qrcodejs/qrcode.min.js"></script>
