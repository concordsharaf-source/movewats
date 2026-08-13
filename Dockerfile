FROM php:8.2-apache

# تمكين mod_rewrite
RUN a2enmod rewrite

# نسخ الملفات إلى مجلد الخادوم
COPY . /var/www/html/

# ضبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# فتح المنفذ 80
EXPOSE 80
