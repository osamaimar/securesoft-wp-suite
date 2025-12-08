# كيفية تشغيل CI/CD Pipeline

## الطرق المختلفة لتشغيل CI/CD

### 1. التشغيل التلقائي (Automatic) ✅

**يعمل تلقائياً عند:**

- **Push** إلى الفروع: `main`, `master`, أو `develop`
- **Pull Request** إلى الفروع: `main`, `master`, أو `develop`
- **إنشاء Release** جديد على GitHub

**شروط التشغيل:**
- يجب أن تكون التغييرات في:
  - `wp-content/plugins/ss-core-licenses/**` (أي ملف في المكوّن)
  - `.github/workflows/**` (ملفات CI/CD)

**مثال:**
```bash
git add .
git commit -m "feat: Add new feature"
git push origin main
# ✅ CI/CD سيعمل تلقائياً بعد الـ push
```

---

### 2. التشغيل اليدوي من GitHub (Manual Trigger) 🖱️

**الخطوات:**

1. اذهب إلى مستودع GitHub
2. اضغط على تبويب **"Actions"** في الأعلى
3. اختر workflow **"CI/CD Pipeline"** من القائمة الجانبية
4. اضغط على زر **"Run workflow"** في الأعلى
5. اختر الفرع (Branch) الذي تريد تشغيله عليه
6. اضغط **"Run workflow"**

**متى تستخدمه:**
- عندما تريد اختبار CI/CD بدون عمل commit
- عندما تريد إعادة تشغيل workflow فاشل
- عندما تريد اختبار workflow بعد تعديله

---

### 3. التحقق من حالة CI/CD

**من GitHub:**

1. اذهب إلى **Actions** في مستودع GitHub
2. ستجد قائمة بجميع الـ workflows التي تم تشغيلها
3. اضغط على أي workflow لرؤية التفاصيل:
   - ✅ **أخضر** = نجح
   - ❌ **أحمر** = فشل
   - 🟡 **أصفر** = قيد التشغيل

**من Terminal:**

```bash
# التحقق من آخر commit
git log --oneline -1

# التحقق من حالة remote
git remote -v
```

---

### 4. عرض نتائج CI/CD

**في صفحة Actions:**

1. اضغط على workflow run الذي تريد رؤيته
2. ستجد قائمة بالـ Jobs:
   - ✅ PHP Syntax Check
   - ✅ WordPress Coding Standards
   - ✅ Security Scan
   - ✅ Build Plugin
   - ✅ Create Release (عند إنشاء release)

3. اضغط على أي job لرؤية:
   - **Logs**: سجلات التنفيذ
   - **Artifacts**: ملفات البناء (ZIP packages)

---

### 5. تحميل Build Artifacts

**بعد نجاح Build Plugin job:**

1. اذهب إلى workflow run
2. اضغط على **"Build Plugin"** job
3. في أسفل الصفحة، ستجد **"Artifacts"**
4. اضغط على اسم الـ artifact (مثل: `ss-core-licenses-1.0.1`)
5. سيتم تحميل ملف ZIP للمكوّن

**ملاحظة:** Artifacts متاحة لمدة 30 يوم فقط

---

### 6. استكشاف الأخطاء (Troubleshooting)

**إذا فشل workflow:**

1. **افتح workflow run**
2. **اضغط على Job الفاشل** (علامة ❌)
3. **اقرأ Logs** لمعرفة السبب

**أخطاء شائعة:**

- ❌ **PHP Syntax Error**: خطأ في صيغة PHP
  - **الحل**: راجع الملف المذكور في الـ logs

- ❌ **PHPCS Errors**: أخطاء في معايير الكود
  - **الحل**: شغّل `composer run phpcbf` محلياً لإصلاحها

- ❌ **Build Failed**: فشل في بناء المكوّن
  - **الحل**: تحقق من وجود `Version:` في `ss-core-licenses.php`

---

### 7. إعادة تشغيل Workflow

**إذا فشل workflow:**

1. اذهب إلى workflow run
2. اضغط على **"Re-run jobs"** في الأعلى
3. اختر **"Re-run all jobs"** أو job محدد

---

### 8. إشعارات CI/CD

**GitHub يرسل إشعارات عند:**

- ✅ نجاح workflow
- ❌ فشل workflow
- 🔔 تعليق على Pull Request

**يمكنك تفعيل/تعطيل الإشعارات من:**
- Settings → Notifications → Actions

---

## مثال عملي كامل

### السيناريو: إضافة ميزة جديدة

```bash
# 1. إنشاء فرع جديد
git checkout -b feature/new-feature

# 2. إجراء التغييرات
# ... تعديل الملفات ...

# 3. Commit التغييرات
git add .
git commit -m "feat: Add new feature"

# 4. Push إلى GitHub
git push origin feature/new-feature

# 5. إنشاء Pull Request
# اذهب إلى GitHub وأنشئ PR من feature/new-feature إلى main

# ✅ CI/CD سيعمل تلقائياً على Pull Request

# 6. بعد مراجعة الكود، دمج PR
# ✅ CI/CD سيعمل تلقائياً على main بعد الدمج
```

---

## ملخص سريع

| الطريقة | متى تستخدمها | كيفية التشغيل |
|---------|--------------|---------------|
| **تلقائي** | Push/PR/Release | يعمل تلقائياً |
| **يدوي** | اختبار أو إعادة تشغيل | Actions → Run workflow |
| **من Terminal** | لا يمكن | CI/CD يعمل فقط على GitHub |

---

## روابط مفيدة

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Viewing workflow runs](https://docs.github.com/en/actions/monitoring-and-troubleshooting-workflows/viewing-workflow-run-history)
- [Manual workflow triggers](https://docs.github.com/en/actions/using-workflows/manually-running-a-workflow)

---

**ملاحظة:** CI/CD يعمل فقط على GitHub. لا يمكن تشغيله محلياً، لكن يمكنك تشغيل الفحوصات محلياً باستخدام:

```bash
cd wp-content/plugins/ss-core-licenses
composer install
composer run lint
composer run phpcs
```

