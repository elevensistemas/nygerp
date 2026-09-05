from pathlib import Path
text = Path('resources/views/layouts/app.blade.php').read_text()
needle = '{{-- TRAFICO / GESTION --}}'
idx = text.index(needle)
print(repr(text[idx-60:idx]))
