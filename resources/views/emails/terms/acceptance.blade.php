<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Confirma tus terminos</title>
</head>
<body style="font-family: sans-serif; background: #f8f9fa; padding: 2rem;">
  <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,.08);">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Hola {{ $user->name }}</h1>
    <p>Antes de poder acceder al sistema necesitamos que confirmes que leiste y aceptaste los <strong>terminos y condiciones</strong> y la <strong>politica de privacidad</strong>.</p>
    <p>Para continuar hace clic en <strong>Acepto</strong>, revisa el texto en la web y confirma con el checkbox.</p>
    <div style="margin: 1.5rem 0; padding: 1rem; background: #f1f3f5; border: 1px solid #e9ecef; border-radius: 6px;">
      <p style="margin: 0 0 0.35rem 0;"><strong>Usuario:</strong> {{ $user->email }}</p>
      @if($plainPassword)
        <p style="margin: 0;"><strong>Contrasena:</strong> {{ $plainPassword }}</p>
      @else
        <p style="margin: 0; color: #6c757d;">La contrasena fue definida previamente.</p>
      @endif
    </div>
    <div style="text-align: center; margin: 2rem 0;">
      <a href="{{ $acceptLink }}" style="background: #0d6efd; color: white; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 4px; display: inline-block;">Acepto</a>
    </div>
    <div style="font-size: 0.95rem; color: #495057; margin-bottom: 1rem;">
      <strong>Si el botón no funciona</strong>, copiá y pegá este enlace en tu navegador:
      <div style="margin-top: 0.5rem; padding: 0.75rem; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; word-break: break-all;">
        {{ $acceptLink }}
      </div>
    </div>
    <p style="font-size: 0.9rem; color: #6c757d;">Si no reconoces este correo, ignoralo.</p>
  </div>
</body>
</html>
