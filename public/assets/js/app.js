document.addEventListener('click', (event) => {
  const opener = event.target.closest('[data-modal]');
  if (opener) document.getElementById(opener.dataset.modal)?.showModal();
  const closer = event.target.closest('[data-close]');
  if (closer) closer.closest('dialog')?.close();
});
document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => {
  if (event.target === dialog) dialog.close();
}));
