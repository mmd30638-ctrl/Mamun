/**
 * 10.98.349 loader — পুরনো ক্যাশড HTML এই ফাইলনেম চাইলে PHP ডেস্ক আনে।
 */
(function () {
    if (window.AME_PURCHASE_DESK_349 || window.AME_PURCHASE_DESK_348 || window.AME_PURCHASE_DESK_346 || window.AME_PURCHASE_DESK_343) return;
    if (document.querySelector('script[src*="ame-pdesk-349"], script[src*="ame-pdesk-348"], script[src*="ame-pdesk-346"], script[src*="ame-pdesk-343"]')) return;
    var s = document.createElement('script');
    s.src = '/assets/js/ame-pdesk-349.php?v=10.98.351';
    s.defer = true;
    (document.head || document.documentElement).appendChild(s);
})();
