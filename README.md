# Contact Hub

A fast, accessible floating contact widget for WordPress.
WhatsApp, Telegram, Eitaa, Bale, Rubika, Instagram, Messenger, Viber, SMS, phone, email and custom links, in one tidy panel.

**No external requests. No tracking. No remote fonts, CSS or icons.**

[فارسی ↓](#راهنمای-فارسی)

## Features

- Pulsing ring, spring-in button and staggered panel animation (optional, respects *reduced motion*)
- Keyboard accessible and screen-reader friendly (`aria-expanded`, focus handling, <kbd>Esc</kbd> to close)
- RTL ready, light / dark / auto color modes, mobile friendly, safe-area aware
- Business hours with an online/offline status, evaluated in the browser so cached pages stay correct
- Prefilled messages with `{page_title}` and `{page_url}` (WhatsApp, SMS, email)
- Persian digits accepted in phone numbers; `09121234567` becomes `98912…` automatically
- Show/hide by device, content type, page ID and WooCommerce cart/checkout
- Click events pushed to `dataLayer` / `gtag` (optional)
- Shortcode `[contact_hub_buttons]` to place the buttons inside a page
- English and Persian (`fa_IR`) translations included

## Installation

1. Download `contact-hub.zip` from the [latest release](../../releases/latest).
   Do **not** use GitHub's *Download ZIP* button: it names the folder `contact-hub-main`.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, activate.
3. Open **Settings → Contact Hub**, add your channels and save.

Requires WordPress 5.8+ and PHP 7.4+.

## Developer hooks

```php
// Decide where the widget appears.
add_filter( 'contact_hub_should_render', function ( $show, $settings ) {
	return is_page( 'landing' ) ? false : $show;
}, 10, 2 );

// Customize phone normalization (digits only, no plus).
add_filter( 'contact_hub_phone_digits', function ( $digits, $original ) {
	return $digits;
}, 10, 2 );
```

Event fired on click:

```js
document.addEventListener('contacthub:click', e => console.log(e.detail.channel));
```

## Releasing

Update the version in `contact-hub.php` and `readme.txt`, then:

```bash
git tag v2.2.0 && git push --tags
```

The GitHub Action builds `contact-hub.zip` and attaches it to a new release.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

## راهنمای فارسی

**Contact Hub** یک ویجت تماس شناور، سریع و در دسترس برای وردپرس است: واتس‌اپ، تلگرام، ایتا، بله، روبیکا، اینستاگرام، مسنجر، وایبر، پیامک، تماس، ایمیل و لینک دلخواه در یک پنل.
**بدون درخواست خارجی، بدون ردیابی، بدون فونت یا CSS از سرور دیگر.**

### نصب

1. فایل `contact-hub.zip` را از بخش [Releases](../../releases/latest) دانلود کنید (از دکمه‌ی Download ZIP خود گیت‌هاب استفاده نکنید).
2. در وردپرس: **افزونه‌ها ← افزودن ← بارگذاری افزونه**، فایل را انتخاب و فعال کنید.
3. به **تنظیمات ← Contact Hub** بروید، کانال‌ها را اضافه کنید و ذخیره کنید.

### امکانات

- انیمیشن حلقه‌ی پالس، ورود فنری دکمه و ظاهر شدن پلکانی ردیف‌ها (اختیاری و سازگار با «کاهش حرکت»)
- پشتیبانی کامل از RTL، حالت روشن/تیره/خودکار و موبایل
- ساعات کاری با وضعیت آنلاین/آفلاین (سازگار با کش)
- پیام پیش‌فرض با `{page_title}` و `{page_url}`
- پذیرش ارقام فارسی در شماره‌ها و تبدیل خودکار `09121234567` به قالب بین‌المللی
- قوانین نمایش بر اساس دستگاه، نوع محتوا، شناسه‌ی برگه و سبد/تسویه‌حساب ووکامرس
- ارسال رویداد کلیک به `dataLayer` و `gtag` (اختیاری)
- شورت‌کد `[contact_hub_buttons]` برای قرار دادن دکمه‌ها داخل صفحه

### مجوز

GPL-2.0-or-later. فایل [LICENSE](LICENSE) را ببینید.
