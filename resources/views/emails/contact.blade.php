<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Nauja žinutė</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #C9A84C;">Nauja žinutė iš padeliotunyrai.lt</h2>
  <table style="width:100%; border-collapse:collapse;">
    <tr><td style="padding:8px 0; color:#666;">Vardas:</td><td style="padding:8px 0;"><strong>{{ $contact->name }}</strong></td></tr>
    <tr><td style="padding:8px 0; color:#666;">El. paštas:</td><td style="padding:8px 0;"><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></td></tr>
  </table>
  <div style="margin-top:20px; padding:15px; background:#f5f5f5; border-left:4px solid #C9A84C;">
    <p style="margin:0; line-height:1.6;">{{ $contact->message }}</p>
  </div>
  <p style="margin-top:20px; color:#999; font-size:12px;">Išsiųsta iš padeliotunyrai.lt kontaktų formos</p>
</body>
</html>
