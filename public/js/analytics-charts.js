/** Analytics Charts
 * Fetches JSON from /analytics/data and renders bar + donut charts
 * Guards ApexCharts availability and missing containers gracefully
 */
(function(){
  function ready(fn){ if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  function q(id){ return document.getElementById(id); }

  function normalizeLabel(str){
    if(!str) return str;
    var m = {
      'Kuota': 'Quota',
      'Sisa Kuota': 'Remaining Quota',
      'Realisasi': 'Actual',
      'Belum Direalisasi': 'Not Yet Realized',
      'Penerimaan': 'Receipts',
      'Pengiriman': 'Shipments'
    };
    return m[str] || str;
  }

  function normalizeSeries(series){
    if(!Array.isArray(series)) return series;
    return series.map(function(s){
      if(s && typeof s === 'object' && 'name' in s){
        s.name = normalizeLabel(s.name);
      }
      return s;
    });
  }

  function normalizeLabels(arr){
    if(!Array.isArray(arr)) return arr;
    return arr.map(function(x){ return normalizeLabel(x); });
  }

  function renderBar(el, data){ if(!el || !window.ApexCharts) return; new ApexCharts(el, {
      chart:{ type:'bar', height: el.clientHeight || 320, animations:{enabled:true} },
      series: normalizeSeries(data.series || []),
      xaxis: { categories: data.categories || [] },
      plotOptions: { bar: { columnWidth: '50%', endingShape: 'rounded' } },
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      colors: ['#60a5fa','#2563eb']
  }).render(); }

  function renderDonut(el, data){ if(!el || !window.ApexCharts) return; new ApexCharts(el, {
      chart:{ type:'donut', height: el.clientHeight || 320, animations:{enabled:true} },
      series: (data && data.series) || [0,0],
      labels: normalizeLabels((data && data.labels) || ['Actual','Remaining']),
      legend: { position: 'bottom' },
      dataLabels: { enabled:false },
      colors: ['#2563eb','#94a3b8']
  }).render(); }

  function escapeHtml(value){
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, function(ch){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]);
    });
  }

  function formatNumber(value){
    var n = Number(value);
    if (!isFinite(n)) return '0';
    return n.toLocaleString();
  }

  function fillTable(tbodyId, tablePayload, mode, labels){
    var tb = q(tbodyId); if(!tb) return;
    var rows = [];
    if (Array.isArray(tablePayload)) {
      rows = tablePayload;
    } else if (tablePayload && Array.isArray(tablePayload.rows)) {
      rows = tablePayload.rows;
    }
    if(!rows.length){
      tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No data.</td></tr>';
      return;
    }

    tb.innerHTML = rows.map(function(row){
      var primary = Number(row.primary_value) || 0;
      var secondary = Number(row.secondary_value) || 0;
      var percentage = Number(row.percentage) || 0;
      return '<tr>'+
        '<td>'+ escapeHtml(row.quota_number || '') +'</td>'+
        '<td>'+ escapeHtml(row.range_pk || '') +'</td>'+
        '<td class="text-end">'+ formatNumber(row.initial_quota) +'</td>'+
        '<td class="text-end">'+ formatNumber(primary) +'</td>'+
        '<td class="text-end">'+ formatNumber(secondary) +'</td>'+
        '<td class="text-end">'+ percentage.toFixed(2) +'%</td>'+
      '</tr>';
    }).join('');
  }

  function fillHsPkSummary(tbodyId, payload){
    var tb = q(tbodyId); if(!tb) return;
    var rows = (payload && Array.isArray(payload.rows)) ? payload.rows : [];
    if(!rows.length){ tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No data.</td></tr>'; return; }
    tb.innerHTML = rows.map(function(r){
      var approved = Number(r.approved)||0;
      var consumed = Number(r.consumed_until_dec)||0;
      var consumedPct = Number(r.consumed_pct)||0;
      var balance = Number(r.balance_until_dec)||0;
      var balancePct = Number(r.balance_pct)||0;
      var jan = Number(r.consumed_next_jan)||0;
      var pct = function(n){ return (Number(n)||0).toFixed(2)+'%'; };
      return '<tr>'+
        '<td>'+ escapeHtml(r.hs_code||'-') +'</td>'+
        '<td>'+ escapeHtml(r.capacity_label||'') +'</td>'+
        '<td class="text-end">'+ formatNumber(approved) +'</td>'+
        '<td class="text-end">'+ formatNumber(consumed) +'<br><span style="color:#ef4444;font-weight:700">'+ pct(consumedPct) +'</span></td>'+
        '<td class="text-end">'+ formatNumber(balance) +'<br><span class="text-muted">'+ pct(balancePct) +'</span></td>'+
        '<td class="text-end">'+ formatNumber(jan) +'</td>'+
      '</tr>';
    }).join('');
    try{
      var totals = payload.totals || {};
      var tA = document.getElementById('hsPkTotalApproved'); if(tA) tA.textContent = formatNumber(totals.approved||0);
      var tC = document.getElementById('hsPkTotalConsumed'); if(tC) tC.textContent = formatNumber(totals.consumed_until_dec||0);
    }catch(e){}
  }

  window.initAnalyticsCharts = function(cfg){
    ready(function(){
      var barEl = q(cfg.barElId || 'analyticsBar');
      var donutEl = q(cfg.donutElId || 'analyticsDonut');
      var dataUrl = cfg.dataUrl;
      var tbodyId = cfg.tableBodyId || 'analyticsTableBody';
      var defaultMode = cfg.mode || 'actual';
      var defaultLabels = cfg.labels || {};
      if(!dataUrl){ fillTable(tbodyId, [], defaultMode, defaultLabels); return; }

      fetch(dataUrl, { headers: { 'Accept':'application/json' }})
        .then(function(r){ return r.json(); })
        .then(function(json){
          try{ renderBar(barEl, json.bar || {}); }catch(e){}
          try{ renderDonut(donutEl, json.donut || {}); }catch(e){}
          try{
            fillTable(
              tbodyId,
              json.table || [],
              json.mode || defaultMode,
              json.labels || defaultLabels
            );
          }catch(e){}
          try{ fillHsPkSummary('hsPkSummaryBody', (json.summary && json.summary.hs_pk) ? json.summary.hs_pk : {rows:[]}); }catch(e){}
          try {
            var sum = json.summary || {};
            var nf = function(n){ n = Number(n)||0; return n.toLocaleString(); };
            var byId = function(id){ return document.getElementById(id); };
            var m = {
              kpiAllocation: sum.total_allocation,
              kpiForecast: sum.total_forecast_consumed,
              kpiActual: sum.total_actual_consumed,
              kpiInTransit: sum.total_in_transit,
              kpiForecastRem: sum.total_forecast_remaining,
              kpiActualRem: sum.total_actual_remaining
            };
            Object.keys(m).forEach(function(k){ var el = byId(k); if(el) el.textContent = nf(m[k]); });
          } catch(e){}
        })
        .catch(function(){ fillTable(tbodyId, [], defaultMode, defaultLabels); });
    });
  };
})();
