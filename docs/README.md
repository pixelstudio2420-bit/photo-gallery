# 📚 Photo Gallery — Documentation

เอกสารทั้งหมดสำหรับระบบ Photo Gallery (Laravel 12 + Tailwind CSS)

> 🌐 **เปิดดูเวอร์ชันเว็บสวย ๆ ที่**: https://YOUR-USERNAME.github.io/photo-gallery-tailwind/

---

## 📂 โครงสร้างเอกสาร

| ไฟล์ | ภาษา | รายละเอียด |
|------|:---:|------------|
| [`index.html`](./index.html) | 🇹🇭 | Landing page — ภาพรวมเอกสารทั้งหมด |
| [`deployment/hostinger.html`](./deployment/hostinger.html) | 🇹🇭 | **🟣 คู่มือ Hostinger Step-by-Step** — จากจ่ายเงินจนเว็บขึ้น ~35 นาที |
| [`deployment/guide.html`](./deployment/guide.html) | 🇹🇭 | **คู่มือเปิดใช้งาน Online** — 4 เส้นทาง (Budget → Enterprise) |
| [`INSTALLATION.md`](./INSTALLATION.md) | 🇹🇭 | คู่มือติดตั้งแบบเต็ม 18 หัวข้อ |
| [`SCALING.html`](./SCALING.html) | 🇬🇧 | Scaling to 50,000+ users |
| [`SCALING.md`](./SCALING.md) | 🇬🇧 | Scaling guide (Markdown version) |
| [`deployment/`](./deployment/) | 🇬🇧 | Deployment scripts + configs |

---

## 🌐 ใช้งานเป็น GitHub Pages

### ตั้งค่าครั้งแรก

1. Push โค้ดทั้งหมดขึ้น GitHub
2. ไปที่ repo → **Settings** → **Pages**
3. Source: **Deploy from a branch**
4. Branch: **main** — Folder: **/docs** → **Save**
5. รอ ~1-2 นาที แล้วเปิด `https://<username>.github.io/<repo>/`

### อัพเดทเอกสาร

แค่ push การเปลี่ยนแปลงในโฟลเดอร์ `docs/` — GitHub Pages จะ rebuild อัตโนมัติใน ~30 วินาที

---

## 🖥 ดูในเครื่อง (Local Preview)

```bash
# วิธีที่ 1: เปิดไฟล์โดยตรง
start docs/index.html            # Windows
open docs/index.html             # Mac
xdg-open docs/index.html         # Linux

# วิธีที่ 2: ใช้ local web server (แนะนำ)
cd docs && python -m http.server 8080
# เปิด: http://localhost:8080

# วิธีที่ 3: ผ่าน XAMPP (เพราะโปรเจคอยู่ใน htdocs อยู่แล้ว)
# http://localhost/photo-gallery-tailwind/docs/
```

---

## 📝 ไฟล์ที่ไม่ควรถูก serve

ไฟล์เหล่านี้อยู่ใน `.gitignore` หรือถูก exclude ใน `_config.yml`:
- `*.sh` — deployment scripts (ไม่ต้องให้ download)
- `*.conf` — server configs
- `Gemfile`, `node_modules`, `vendor`

---

## 🎨 ปรับแต่ง

- **สีหลัก**: แก้ `--clr-primary` ใน `index.html` และ `deployment/guide.html`
- **Font**: เปลี่ยน `Prompt` เป็น font อื่นผ่าน Google Fonts
- **โลโก้**: แทน emoji 📸 ด้วย `<img>` ในส่วน Hero
- **เพิ่มภาษาอื่น**: copy ไฟล์ → แปล → เพิ่ม language switcher

---

## 🔗 Links

- [Project Repository](../) — source code
- [Laravel Docs](https://laravel.com/docs/12.x)
- [GitHub Pages Docs](https://docs.github.com/pages)
