from PIL import Image

path = r"D:\projects\toys\loader_frame_04.jpg"
im = Image.open(path).convert("RGB")
im.thumbnail((44, 90))
w, h = im.size
print(im.size)

for y in range(0, h, 2):
    line = ""
    for x in range(w):
        r, g, b = im.getpixel((x, y))
        line += f"\033[48;2;{r};{g};{b}m  "
    print(line + "\033[0m")
