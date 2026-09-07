<div class="modal-overlay" id="modal-new-allocation">
  <div class="modal-box">
    <div class="modal-head">New Allocation</div>
    <div class="modal-body">
      <div class="field">
        <label>Employee No</label>
        <input type="text" id="na-emp-no" placeholder="e.g. 100024">
      </div>
      <div class="field">
        <label>Assign to Auditor</label>
        <input type="text" id="na-auditor-no" placeholder="Auditor employee no">
      </div>
      <div class="modal-hint">Sets this employee's supervisor and allocates them for <?= h(active_cycle()) ?>.</div>
    </div>
    <div class="modal-foot">
      <button class="btn" onclick="closeModal('modal-new-allocation')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewAllocation()">Confirm</button>
    </div>
  </div>
</div>
<script>
async function submitNewAllocation() {
  const empNo = document.getElementById('na-emp-no').value.trim();
  const auditorNo = document.getElementById('na-auditor-no').value.trim();
  if (!empNo || !auditorNo) { toast('Please fill in both fields.', 'error'); return; }
  const res = await apiPost('api/allocate.php', { emp_no: empNo, auditor_no: auditorNo });
  if (res.ok) {
    toast(res.message || 'Allocation saved.', 'success');
    closeModal('modal-new-allocation');
    setTimeout(() => location.reload(), 600);
  } else {
    toast(res.message || 'Could not save allocation.', 'error');
  }
}
<?php if (isset($_GET['new']) && $_GET['new'] === '1'): ?>
document.addEventListener('DOMContentLoaded', () => openModal('modal-new-allocation'));
<?php endif; ?>
</script>
