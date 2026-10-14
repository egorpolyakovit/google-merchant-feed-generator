import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

root = Path(__file__).resolve().parents[1]
subprocess.run(['php', str(root/'generate.php'), '--output='+str(root/'examples/google-products.xml')], check=True)
xml = ET.parse(root/'examples/google-products.xml').getroot()
assert xml.tag == 'rss'
items = xml.findall('./channel/item')
assert len(items) == 2, f'Expected 2 products, got {len(items)}'
ns = '{http://base.google.com/ns/1.0}'
for item in items:
    for name in ('id', 'title', 'description', 'link', 'image_link', 'price', 'brand', 'availability'):
        assert item.findtext(ns+name), f'Missing {name}'
    assert item.findtext(ns+'availability') == 'in_stock'
    assert item.findtext(ns+'price').endswith(' RUB')
assert all('crown' not in (item.findtext(ns+'title') or '').lower() for item in items)
print('PASS: valid RSS 2.0 XML; required fields; 2 included, 2 excluded')
