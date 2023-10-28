class BrfScrollSmoother{settings;smoother;constructor(){this.init()}async init(){document.body.classList.contains("brf-backend-view")||this.prepare()}prepare(){switch(this.settings=BRFSCROLLSMOOTHER.toolSettings.find((t=>7==t.id)),this.settings?(this.settings=this.settings.settings,this.settings.provider||(this.settings.provider="gsap")):this.settings={settings:{provider:"gsap",smooth:1,smoothTouch:!1,effects:!1}},this.settings.provider){case"gsap":if(document.body.classList.contains("brf-scroll-smoother-disabled"))return;gsap.registerPlugin(ScrollTrigger,ScrollSmoother),ScrollTrigger.config({ignoreMobileResize:!0}),window.bricksSmoothScroll=()=>!1,document.querySelectorAll("[data-brf-fixed]").forEach((t=>{document.body.append(t)}));!!this.settings.adjustFixedElements&&this.settings.adjustFixedElements&&this.adjustFixedPositions(),this.run(this.settings.provider);break;case"lenis":this.run(this.settings.provider)}}isTouchDevice(){return"ontouchstart"in window||navigator.maxTouchPoints>0||navigator.msMaxTouchPoints>0}run(t){switch(t){case"gsap":const t=document.querySelector("#smooth-content"),e=this.settings.smooth?this.settings.smooth:1,s=this.settings.smoothTouch?this.settings.smoothTouch:null,i=!!this.settings.effects&&this.settings.effects,o=this.settings.speed?this.settings.speed:1;if(!s&&this.isTouchDevice())return;let n={smooth:e,effects:i,normalizeScroll:!0,ignoreMobileResize:!0,speed:o};1==s&&(n.smoothTouch=e),this.smoother=ScrollSmoother.create(n);new ResizeObserver((t=>{gsap.delayedCall(.2,(()=>{this.smoother.refresh()}))})).observe(t),document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((t=>{t.addEventListener("click",(t=>{t.preventDefault();const e=t.target.closest("a");this.smoother.scrollTo(e.getAttribute("href"),!0,"top 50px")}))})),window.location.hash&&setTimeout((()=>{this.smoother.scrollTo(window.location.hash,!1,"top 50px")}),100);break;case"lenis":document.documentElement.style.scrollBehavior="initial";let r=this.settings.lenisDuration?this.settings.lenisDuration:1.2,h=this.settings.lenisEase?this.settings.lenisEase:"Math.min(1, 1.001 - Math.pow(2, -10 * x))",l=new Function("return (x) => { return  "+h+" }")(),c=this.settings.lenisDirection?this.settings.lenisDirection:"vertical",a=this.settings.lenisGestureDirection?this.settings.lenisGestureDirection:"vertical",u=!this.settings.lenisSmooth||this.settings.lenisSmooth,d=this.settings.lenisMouseMultiplier?this.settings.lenisMouseMultiplier:1,g=!!this.settings.lenisSmoothTouch&&this.settings.lenisSmoothTouch,m=this.settings.lenisTouchMultiplier?this.settings.lenisTouchMultiplier:2,p=!!this.settings.lenisInfinite&&this.settings.lenisInfinite;this.smoother=new Lenis({duration:r,easing:t=>l(t),direction:c,gestureDirection:a,smooth:u,mouseMultiplier:d,smoothTouch:g,touchMultiplier:m,infinite:p});const f=t=>{this.smoother.raf(t),requestAnimationFrame(f)};requestAnimationFrame(f),document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((t=>{t.addEventListener("click",(t=>{t.preventDefault();const e=t.target.closest("a");this.smoother.scrollTo(e.getAttribute("href"),!0,"top 50px")}))})),window.location.hash&&this.smoother.scrollTo(window.location.hash,{immediate:!0})}}adjustFixedPositions(){[].filter.call(document.querySelectorAll("*"),(t=>"fixed"==getComputedStyle(t).position)).forEach((t=>{t.classList.contains("bricks-mobile-menu-wrapper")||document.body.append(t)}))}}var brfScrollSmoother;document.addEventListener("DOMContentLoaded",(()=>{bricksIsFrontend&&"undefined"!=typeof BRFSCROLLSMOOTHER&&(brfScrollSmoother=new BrfScrollSmoother)}));