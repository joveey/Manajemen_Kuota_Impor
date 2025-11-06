(function(){
  const dirty = new Map();
  const chgCount = document.getElementById('chgCount');
  const saveBtn   = document.getElementById('saveChanges');
  const resetBtn  = document.getElementById('resetChanges');

  function markDirty(container){
    const id = container.getAttribute('data-line-id');
    if(!id) return;
    const row = {
      line_id: +id,
      bl:      container.querySelector('.v-bl')?.value || '',
      factory: container.querySelector('.v-factory')?.value || '',
      status:  container.querySelector('.v-status')?.value || '',
      issue_date:  container.querySelector('.v-issue')?.value || null,
      expired:     container.querySelector('.v-expired')?.value || null,
      etd:         container.querySelector('.v-etd')?.value || null,
      eta:         container.querySelector('.v-eta')?.value || null,
      remark:      container.querySelector('.v-remark')?.value || '',
    };
    dirty.set(id, row);
    if (chgCount) chgCount.textContent = dirty.size;
    if (saveBtn) saveBtn.disabled = dirty.size === 0;
  }

  // desktop
  document.querySelectorAll('#voyage-rows tr').forEach(tr=>{
    tr.addEventListener('change', ()=> markDirty(tr));
  });
  // mobile
  document.querySelectorAll('.voyage-card').forEach(card=>{
    card.addEventListener('change', ()=> markDirty(card));
  });

  saveBtn?.addEventListener('click', async ()=>{
    if(dirty.size === 0) return;
    saveBtn.disabled = true;

    const payload = { rows: Array.from(dirty.values()) };
    const resp = await fetch(location.pathname.replace(/\/$/, '') + '/bulk', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(payload)
    });
    if(resp.ok){
      dirty.clear();
      if (chgCount) chgCount.textContent = 0;
      saveBtn.disabled = true;
      alert('Changes saved');
    } else {
      alert('Failed to save');
      saveBtn.disabled = false;
    }
  });

  resetBtn?.addEventListener('click', ()=> location.reload());
})();
