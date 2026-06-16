Python code to generate the thumbnail. This is a placeholder/example to give style/theme of the thumbnails.

```python
python3 -c "
from PIL import Image, ImageDraw
img = Image.new('RGB', (300, 300), color='white')
draw = ImageDraw.Draw(img)
draw.rectangle([2, 2, 297, 297], outline='#999999', width=1)

# Title text placeholder bar
draw.rectangle([15, 15, 220, 32], fill='#cccccc', outline='#aaaaaa', width=1)
draw.text((17, 18), 'Title Text', fill='#666666')

# Intro text lines
for i, width in enumerate([270, 240, 200]):
    y = 45 + i * 18
    draw.rectangle([15, y, 15 + width, y + 11], fill='#dddddd', outline='#bbbbbb', width=1)

# Image placeholder (bottom area)
img_box = [15, 120, 285, 285]
draw.rectangle(img_box, outline='#aaaaaa', width=1, fill='#eeeeee')
draw.line([img_box[0], img_box[1], img_box[2], img_box[3]], fill='#cccccc', width=1)
draw.line([img_box[2], img_box[1], img_box[0], img_box[3]], fill='#cccccc', width=1)
cx = (img_box[0] + img_box[2]) // 2
cy = (img_box[1] + img_box[3]) // 2
draw.text((cx, cy), '[ image ]', fill='#999999', anchor='mm')

output = '/Users/richard/Development/Sites/orbit_dev/wireframe-mockup.png'
img.save(output, 'PNG')
print('Saved to', output)
"
```
