/**
 * সিরিয়াল ৫৯: এআই ডোম শিল্ড (AME PRO)
 * কাজ: ডুপ্লিকেট ডিক্লারেশন এরর ফিক্স করা।
 */

// যদি 'aponDOM' আগে থেকে না থাকে তবেই এটি তৈরি হবে
if (typeof aponDOM === 'undefined') {
    window.aponDOM = function(id) {
        const el = document.getElementById(id);
        if (!el) {
            // ফেইল-সেফ অবজেক্ট রিটার্ন (যাতে এরর না দেয়)
            return { 
                style: {}, 
                innerHTML: "", 
                value: "", 
                classList: { add:()=>{}, remove:()=>{} }, 
                focus:()=>{},
                innerText: ""
            };
        }
        return el;
    };
}

// মেনু কন্ট্রোল ফাংশনগুলোকে গ্লোবাল করা
window.openNav = function() {
    aponDOM("mySidebar").style.width = "270px";
    if (
        window.innerWidth > 768
        && !(document.body && document.body.classList.contains('ame-pos-terminal-page'))
    ) {
        aponDOM("main-content").style.marginLeft = "270px";
    }
};

window.closeNav = function() {
    aponDOM("mySidebar").style.width = "0";
    aponDOM("main-content").style.marginLeft= "0";
};

console.log("🛡️ Aponshop Core Shield V2 Active");