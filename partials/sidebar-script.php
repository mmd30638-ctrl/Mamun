<script>
/**
 * Sidebar Accordion Controller
 */

// একর্ডিয়ন ড্রপডাউন কন্ট্রোল
function toggleAccordion(id) {
    const target = document.getElementById(id);
    if (!target) { return; }
    const parent = target.parentElement;

    // আগে থেকে খোলা অন্য ড্রপডাউন বন্ধ করা
    document.querySelectorAll('.menu-list').forEach(list => {
        if (list.id !== id) {
            list.classList.add('hidden');
            list.parentElement.classList.remove('active-menu');
        }
    });

    target.classList.toggle('hidden');
    parent.classList.toggle('active-menu');
}
</script>