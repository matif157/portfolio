</main>
</div>

<!-- Theme Engine + CRM interactivity (sidebar state, color mode, command palette) -->
<script src="assets/js/app.js"></script>

<?php
// Embed Tawk.to live chat widget if configured
$tawkto_id = get_setting('tawkto_property_id', '');
$tawkto_widget = get_setting('tawkto_widget_id', 'default');
if (!empty($tawkto_id)):
?>
<!-- Tawk.to Script -->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/<?= e($tawkto_id) ?>/<?= e($tawkto_widget ?: 'default') ?>';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!-- End of Tawk.to Script -->
<?php endif; ?>

</body>
</html>