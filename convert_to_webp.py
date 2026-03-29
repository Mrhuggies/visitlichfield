from PIL import Image
import os
import glob

png_files = glob.glob('assets/images/*.png')
print(f'Found {len(png_files)} PNG files')

converted = []
for png_path in png_files:
    try:
        img = Image.open(png_path)
        webp_path = png_path.replace('.png', '.webp')
        img.save(webp_path, 'WEBP', quality=85)
        os.remove(png_path)
        basename = os.path.basename(png_path).replace('.png', '')
        converted.append(basename)
        print(f'✓ Converted: {basename}')
    except Exception as e:
        print(f'✗ Error: {e}')

print(f'\n✅ Converted {len(converted)} images')
print('Image codes:', ', '.join(converted))
