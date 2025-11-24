## 🎯 Purpose of this Pull Request

_Describe what this PR changes and why. Provide context around the issue, feature, or bug it addresses._

- Related issue: #ISSUE_NUMBER (if applicable)

---

## 📝 Summary of Changes

_List the main changes introduced in this PR._

-
-
-

---

## ✔️ Moodle Plugin Checklist

### **Code Quality & Standards**
- [ ] Code follows Moodle coding guidelines: https://moodledev.io/general/development/policies/codingstyle
- [ ] PHPDoc blocks updated where needed
- [ ] No debug statements left (`var_dump`, `error_log`, etc.)
- [ ] Namespace and file structure follow Moodle plugin conventions

### **Functional Changes**
- [ ] New settings added to `settings.php` if required
- [ ] New strings added to `lang/en/pluginname.php`
- [ ] Upgrade steps added to `db/upgrade.php` (if required)
- [ ] Capabilities added/updated in `db/access.php` (if relevant)
- [ ] Events defined/updated in `db/events.php` (if relevant)
- [ ] Backup/restore updated (if plugin stores data)

### **Database & Files**
- [ ] DB schema changes added to `db/install.xml` or `upgrade.php`
- [ ] Privacy API implemented/updated (`classes/privacy/…`)
- [ ] Files API usage follows best practices (if applicable)

### **UI / UX**
- [ ] Templates (`mustache` files) updated with proper escaping
- [ ] Strings are correctly internationalized using `get_string()`
- [ ] Accessibility considerations addressed

---

## 🧪 Testing

### **Manual Tests**
_Describe how to test this change manually._

- Step 1
- Step 2
- Expected result

### **Automated Tests**
- [ ] PHPUnit tests updated/added
- [ ] Behat tests updated/added (if UI-related)

---

## 🔄 Regression Risk

_What areas of the plugin could be impacted by this change?_

---

## 📸 Screenshots (if UI changes)

_Add before/after screenshots to help reviewers._

---

## 🔗 Additional Notes

_Any extra context, implementation detail, or follow-up work._
