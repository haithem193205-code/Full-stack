/**
 * ================================================================
 *  lang.js — Simple English / Arabic (RTL) translation layer
 * ================================================================
 *  No build step, no dependencies. Elements opt in via attributes:
 *
 *    data-i18n="some.key"            → replaces textContent
 *    data-i18n-title="some.key"      → replaces the `title` attribute
 *    data-i18n-placeholder="some.key"→ replaces the `placeholder` attribute
 *    data-i18n-vars='{"name":"Ali"}' → named placeholders inside the string,
 *                                       e.g. "Hi {name}!" → "Hi Ali!"
 *
 *  The chosen language is remembered in localStorage under "flth_lang"
 *  and applied to every page (see the inline <script> in header.php that
 *  sets <html lang/dir> before first paint to avoid a flash).
 * ================================================================
 */

(() => {
  "use strict";

  const STORAGE_KEY = "flth_lang";

  const DICTIONARY = {
    en: {
      "nav.home": "Home",
      "nav.about": "About",
      "nav.contact": "Contact",
      "nav.launchChat": "Start Chat",
      "nav.myChats": "My Chats",
      "nav.logout": "Log Out",
      "nav.login": "Log In",
      "nav.signup": "Sign Up",
      "nav.aiOnline": "AI Online",

      "home.heroLead": "Meet",
      "home.heroSub": "From Learning to Hiring",
      "home.heroDescription":
        "Experience a premium, fast, and intelligent AI assistant designed by FLTH to deliver smooth, natural, and engaging conversations.",

      "home.startChatting": "Start Chatting",
      "home.learnMore": "Learn More",
      "home.statBuilt": "Built by FLTH",
      "home.statRealtime": "Real-Time",
      "home.statResponses": "Fast Responses",
      "home.statAvailability": "24/7 Availability",

      "home.previewAi": "Hello! I’m FLTH AI Assistant.",
      "home.previewUser": "Let’s build something awesome ✨",

      "home.whyEyebrow": "Why Choose FLTH AI",
      "home.whyTitle": "An intelligent assistant built for real conversations",
      "home.whySubtitle":
        "Fast, intuitive, and thoughtfully designed to provide an exceptional user experience.",

      "home.featFastTitle": "Lightning-Fast Responses",
      "home.featFastDesc":
        "Enjoy instant replies with smooth, real-time interactions.",

      "home.featSecureTitle": "Secure by Design",
      "home.featSecureDesc":
        "Built with secure backend architecture and robust input validation.",

      "home.featSmartTitle": "Advanced AI",
      "home.featSmartDesc":
        "Powered by FLTH to deliver natural, intelligent, and helpful responses.",

      "home.featResponsiveTitle": "Fully Responsive",
      "home.featResponsiveDesc":
        "Optimized for desktops, tablets, and mobile devices.",

      "home.featDesignTitle": "Modern Interface",
      "home.featDesignDesc":
        "Elegant glassmorphism design with smooth animations.",

      "home.featCleanTitle": "Scalable Architecture",
      "home.featCleanDesc":
        "Clean, maintainable architecture built for performance and future growth.",

      "home.ctaTitle": "Ready to start chatting?",
      "home.ctaSubtitle":
        "Jump into the FLTH AI Assistant experience in just one click.",

      "home.ctaButton": "Launch AI Assistant",
      "home.contactNote": "Developed by FLTH • Contact:",

      "footer.tagline": "Modern AI conversations, crafted for the web.",

      "chat.newChat": "New Chat",
      "chat.delete": "Delete",
      "chat.noConversations":
        "No conversations yet. Start your first chat!",
      "chat.online": "Online • Ready to assist",
      "chat.deleteConversation": "Delete Conversation",
      "chat.deleteMessage": "Delete Message",
      "chat.removeAttachment": "Remove Attachment",
      "chat.attach": "Attach a file or image",
      "chat.stop": "Stop generating",
      "chat.send": "Send",
      "chat.inputPlaceholder": "Type your message...",
      "chat.disclaimer":
        "AI can make mistakes. Always verify important information.",

      "chat.greeting":
        "👋 Hi {name}! I'm your FLTH AI Assistant. Ask me anything—from quick questions to in-depth discussions. I'm here to help.",

      "chat.newChatGreeting":
        "👋 Hello! What would you like to talk about today?",

      "chat.loadedGreeting":
        "👋 Welcome back! What would you like to discuss?",

      "chat.confirmDeleteMessage":
        "Are you sure you want to delete this message? This action cannot be undone.",

      "chat.toastMessageDeleted": "Message deleted successfully.",
      "chat.toastMessageDeleteFailed": "Unable to delete the message.",
      "chat.toastConversationDeleted": "Conversation deleted successfully.",
      "chat.toastConversationDeleteFailed":
        "Unable to delete the conversation.",
      "chat.toastLoadFailed": "Unable to load the conversation.",
      "chat.toastSendFailed": "Couldn't connect to the AI Assistant.",
      "chat.toastGenericError":
        "Something went wrong. Please try again.",
      "chat.toastCancelled": "Message generation cancelled.",
      "chat.toastUnsupportedFile":
        "This file type isn't supported yet. Please upload an image or a supported text file (.txt, .md, .csv, .json).",
      "chat.toastFileTooLarge":
        "The selected file exceeds the maximum size of 5 MB.",
      "chat.demoMode": "Demo Mode",
    },
        ar: {
      "nav.home": "الرئيسية",
      "nav.about": "من نحن",
      "nav.contact": "تواصل معنا",
      "nav.launchChat": "ابدأ المحادثة",
      "nav.myChats": "محادثاتي",
      "nav.logout": "تسجيل الخروج",
      "nav.login": "تسجيل الدخول",
      "nav.signup": "إنشاء حساب",
      "nav.aiOnline": "الذكاء الاصطناعي متصل",

      "home.heroLead": "تعرّف على",
      "home.heroSub": "من التعلّم إلى التوظيف",
      "home.heroDescription":
        "استمتع بتجربة محادثة ذكية وسريعة واحترافية، صُممت وطُورت بواسطة FLTH لتوفير تواصل طبيعي وسلس بأعلى جودة.",

      "home.startChatting": "ابدأ المحادثة",
      "home.learnMore": "اعرف المزيد",

      "home.statBuilt": "تطوير FLTH",
      "home.statRealtime": "استجابة فورية",
      "home.statResponses": "ردود سريعة",
      "home.statAvailability": "متاح على مدار الساعة",

      "home.previewAi": "أهلاً! أنا مساعدك الذكي.",
      "home.previewUser": "يلا نبني حاجة رائعة ✨",

      "home.whyEyebrow": "لماذا تختار FLTH AI؟",
      "home.whyTitle": "مساعد ذكي مصمم لمحادثات طبيعية وفعّالة",
      "home.whySubtitle":
        "سريع، ذكي، ومصمم بعناية لتقديم أفضل تجربة استخدام.",

      "home.featFastTitle": "استجابة فائقة السرعة",
      "home.featFastDesc":
        "احصل على ردود فورية مع تجربة تفاعلية سلسة.",

      "home.featSecureTitle": "أمان متكامل",
      "home.featSecureDesc":
        "يعتمد على بنية خلفية آمنة ومعالجة قوية للبيانات والمدخلات.",

      "home.featSmartTitle": "ذكاء اصطناعي متقدم",
      "home.featSmartDesc":
        "مدعوم بتقنيات FLTH لتقديم إجابات طبيعية ودقيقة ومفيدة.",

      "home.featResponsiveTitle": "واجهة متجاوبة",
      "home.featResponsiveDesc":
        "تعمل بكفاءة على الهواتف والأجهزة اللوحية وأجهزة الكمبيوتر.",

      "home.featDesignTitle": "تصميم عصري",
      "home.featDesignDesc":
        "واجهة أنيقة بتأثير Glassmorphism مع انتقالات سلسة.",

      "home.featCleanTitle": "بنية برمجية احترافية",
      "home.featCleanDesc":
        "هيكل منظم وقابل للتطوير لضمان الأداء والاستقرار.",

      "home.ctaTitle": "هل أنت مستعد لبدء المحادثة؟",
      "home.ctaSubtitle":
        "ابدأ تجربة FLTH AI Assistant بضغطة واحدة.",
      "home.ctaButton": "تشغيل المساعد الذكي",

      "home.contactNote": "تم تطويره بواسطة FLTH • للتواصل:",

      "footer.tagline":
        "تجربة محادثة بالذكاء الاصطناعي، مصممة خصيصًا للويب.",

      "chat.newChat": "محادثة جديدة",
      "chat.delete": "حذف",
      "chat.noConversations":
        "لا توجد أي محادثات حتى الآن. ابدأ أول محادثة لك.",
      "chat.online": "متصل • جاهز للمساعدة",
      "chat.deleteConversation": "حذف المحادثة",
      "chat.deleteMessage": "حذف الرسالة",
      "chat.removeAttachment": "إزالة المرفق",
      "chat.attach": "إرفاق ملف أو صورة",
      "chat.stop": "إيقاف التوليد",
      "chat.send": "إرسال",
      "chat.inputPlaceholder": "اكتب رسالتك هنا...",
      "chat.disclaimer":
        "قد يقدم الذكاء الاصطناعي معلومات غير دقيقة أحيانًا، لذا يُرجى التحقق من المعلومات المهمة قبل الاعتماد عليها.",

      "chat.greeting":
        "👋 مرحبًا {name}! أنا مساعد FLTH الذكي. اسألني أي شيء، سواء كان سؤالًا سريعًا أو موضوعًا متعمقًا، وسأكون سعيدًا بمساعدتك.",

      "chat.newChatGreeting":
        "👋 أهلًا بك! ما الذي ترغب في التحدث عنه اليوم؟",

      "chat.loadedGreeting":
        "👋 سعيد بعودتك! كيف يمكنني مساعدتك اليوم؟",

      "chat.confirmDeleteMessage":
        "هل أنت متأكد من حذف هذه الرسالة؟ لا يمكن التراجع عن هذا الإجراء.",

      "chat.toastMessageDeleted":
        "تم حذف الرسالة بنجاح.",

      "chat.toastMessageDeleteFailed":
        "تعذر حذف الرسالة.",

      "chat.toastConversationDeleted":
        "تم حذف المحادثة بنجاح.",

      "chat.toastConversationDeleteFailed":
        "تعذر حذف المحادثة.",

      "chat.toastLoadFailed":
        "تعذر تحميل المحادثة.",

      "chat.toastSendFailed":
        "تعذر الاتصال بالمساعد الذكي.",

      "chat.toastGenericError":
        "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",

      "chat.toastCancelled":
        "تم إيقاف إنشاء الرسالة.",

      "chat.toastUnsupportedFile":
        "نوع الملف غير مدعوم حاليًا. يُرجى رفع صورة أو أحد الملفات النصية المدعومة (.txt, .md, .csv, .json).",

      "chat.toastFileTooLarge":
        "حجم الملف يتجاوز الحد الأقصى المسموح به (5 ميجابايت).",

      "chat.demoMode": "الوضع التجريبي",
    },
      };

  function currentLang() {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === "ar" ? "ar" : "en";
  }

  function t(key, vars) {
    const lang = currentLang();

    let str =
      (DICTIONARY[lang] && DICTIONARY[lang][key]) ||
      DICTIONARY.en[key] ||
      key;

    if (vars) {
      Object.keys(vars).forEach((name) => {
        str = str.replace(new RegExp(`\\{${name}\\}`, "g"), vars[name]);
      });
    }

    return str;
  }

  function applyTranslations(lang) {
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";

    document.body.classList.toggle("lang-ar", lang === "ar");

    document.querySelectorAll("[data-i18n]").forEach((el) => {
      const key = el.getAttribute("data-i18n");

      let vars = null;
      const rawVars = el.getAttribute("data-i18n-vars");

      if (rawVars) {
        try {
          vars = JSON.parse(rawVars);
        } catch (e) {
          vars = null;
        }
      }

      el.textContent = t(key, vars);
    });

    document.querySelectorAll("[data-i18n-title]").forEach((el) => {
      el.setAttribute(
        "title",
        t(el.getAttribute("data-i18n-title"))
      );
    });

    document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
      el.setAttribute(
        "placeholder",
        t(el.getAttribute("data-i18n-placeholder"))
      );
    });

    const label = document.getElementById("langToggleLabel");

    if (label) {
      // The button displays the language the user can switch to.
      label.textContent = lang === "ar" ? "English" : "العربية";
    }
  }

  function setLang(lang) {
    localStorage.setItem(STORAGE_KEY, lang);

    applyTranslations(lang);

    document.dispatchEvent(
      new CustomEvent("flth:langchange", {
        detail: { lang },
      })
    );
  }

  function init() {
    applyTranslations(currentLang());

    const toggle = document.getElementById("langToggle");

    if (toggle) {
      toggle.addEventListener("click", () => {
        setLang(currentLang() === "ar" ? "en" : "ar");
      });
    }
  }

  window.FLTH_I18N = {
    t,
    setLang,
    getLang: currentLang,
  };

  document.addEventListener("DOMContentLoaded", init);
})();