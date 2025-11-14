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

  function renderBar(el, data){
    if(!el || !window.ApexCharts) return null;
    var chart = new ApexCharts(el, {
      chart:{ type:'bar', height: el.clientHeight || 320, animations:{enabled:true} },
      series: normalizeSeries(data.series || []),
      xaxis: { categories: data.categories || [] },
      plotOptions: { bar: { columnWidth: '50%', endingShape: 'rounded' } },
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      colors: ['#60a5fa','#2563eb']
    });
    chart.render();
    return chart;
  }

  function renderDonut(el, data){
    if(!el || !window.ApexCharts) return null;
    var chart = new ApexCharts(el, {
      chart:{ type:'donut', height: el.clientHeight || 320, animations:{enabled:true} },
      series: (data && data.series) || [0,0],
      labels: normalizeLabels((data && data.labels) || ['Actual','Remaining']),
      legend: { position: 'bottom' },
      dataLabels: { enabled:false },
      colors: ['#2563eb','#94a3b8']
    });
    chart.render();
    return chart;
  }

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

  function setText(id, text){ var el = document.getElementById(id); if(el) el.textContent = text; }

  function updateQuery(url, param, value){
    try {
      var u = new URL(url, window.location.origin);
      if (value === null || value === undefined || value === '') {
        u.searchParams.delete(param);
      } else {
        u.searchParams.set(param, value);
      }
      return u.toString();
    } catch(e){
      // Fallback for relative URLs without base
      var hasQ = url.indexOf('?') !== -1;
      var base = url.split('#')[0];
      var hash = url.indexOf('#') !== -1 ? url.slice(url.indexOf('#')) : '';
      var params = (hasQ ? base.split('?')[1] : '') || '';
      var path = hasQ ? base.split('?')[0] : base;
      var sp = new URLSearchParams(params);
      if (value === null || value === undefined || value === '') sp.delete(param); else sp.set(param, value);
      var qs = sp.toString();
      return path + (qs ? ('?' + qs) : '') + hash;
    }
  }

  window.initAnalyticsCharts = function(cfg){
    ready(function(){
      var barEl = q(cfg.barElId || 'analyticsBar');
      var donutEl = q(cfg.donutElId || 'analyticsDonut');
      var dataUrl = cfg.dataUrl;
      var tbodyId = cfg.tableBodyId || 'analyticsTableBody';
      var barMode = (cfg.mode || 'actual');
      var donutMode = (cfg.mode || 'actual');
      var defaultLabels = cfg.labels || {};
      var barChart = null; var donutChart = null;

      function renderBarData(json){
        try{ if (barChart && barChart.destroy) { barChart.destroy(); } }catch(e){}
        if (barEl) { barEl.innerHTML = ''; }
        try{ barChart = renderBar(barEl, (json && json.bar) || {}); }catch(e){}
      }

      function renderDonutData(json){
        try{ if (donutChart && donutChart.destroy) { donutChart.destroy(); } }catch(e){}
        if (donutEl) { donutEl.innerHTML = ''; }
        try{ donutChart = renderDonut(donutEl, (json && json.donut) || {}); }catch(e){}
      }

      function applyBarModeUI(mode){
        var titleMode = mode === 'forecast' ? 'Forecast' : 'Actual';
        setText('chartModeTitle', titleMode);
        setText('chartModeBadge', titleMode + ' Based');
        var f = q('modeChipForecast'); var a = q('modeChipActual');
        if (f && a){
          if (mode === 'forecast') { f.classList.add('analytics-mode__chip--active'); a.classList.remove('analytics-mode__chip--active'); }
          else { a.classList.add('analytics-mode__chip--active'); f.classList.remove('analytics-mode__chip--active'); }
        }
      }

      function applyDonutModeUI(mode){
        var titleMode = mode === 'forecast' ? 'Forecast' : 'Actual';
        setText('donutModeTitle', titleMode);
        var f2 = q('modeChipForecast2'); var a2 = q('modeChipActual2');
        if (f2 && a2){
          if (mode === 'forecast') { f2.classList.add('analytics-mode__chip--active'); a2.classList.remove('analytics-mode__chip--active'); }
          else { a2.classList.add('analytics-mode__chip--active'); f2.classList.remove('analytics-mode__chip--active'); }
        }
      }

      function loadInitial(){
        if(!dataUrl){ fillTable(tbodyId, [], barMode, defaultLabels); return; }
        fetch(dataUrl, { headers: { 'Accept':'application/json' }})
          .then(function(r){ return r.json(); })
          .then(function(json){
            renderBarData(json);
            renderDonutData(json);
            try{
              fillTable(
                tbodyId,
                json.table || [],
                json.mode || barMode,
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
            // Initialize both UIs with initial mode
            barMode = json.mode || barMode;
            donutMode = json.mode || donutMode;
            applyBarModeUI(barMode);
            applyDonutModeUI(donutMode);
          })
          .catch(function(){ fillTable(tbodyId, [], barMode, defaultLabels); });
      }

      function switchBarMode(mode){
        barMode = (mode === 'forecast' || mode === 'actual') ? mode : 'actual';
        applyBarModeUI(barMode);
        var url = updateQuery(dataUrl, 'mode', barMode);
        fetch(url, { headers: { 'Accept':'application/json' }})
          .then(function(r){ return r.json(); })
          .then(function(json){ renderBarData(json); })
          .catch(function(){ /* ignore */ });
      }

      function switchDonutMode(mode){
        donutMode = (mode === 'forecast' || mode === 'actual') ? mode : 'actual';
        applyDonutModeUI(donutMode);
        var url = updateQuery(dataUrl, 'mode', donutMode);
        fetch(url, { headers: { 'Accept':'application/json' }})
          .then(function(r){ return r.json(); })
          .then(function(json){ renderDonutData(json); })
          .catch(function(){ /* ignore */ });
      }

      var fBtn = q('modeChipForecast');
      var aBtn = q('modeChipActual');
      var fBtn2 = q('modeChipForecast2');
      var aBtn2 = q('modeChipActual2');
      if (fBtn) fBtn.addEventListener('click', function(){ switchBarMode('forecast'); });
      if (aBtn) aBtn.addEventListener('click', function(){ switchBarMode('actual'); });
      if (fBtn2) fBtn2.addEventListener('click', function(){ switchDonutMode('forecast'); });
      if (aBtn2) aBtn2.addEventListener('click', function(){ switchDonutMode('actual'); });

      // Initial load
      applyBarModeUI(barMode);
      applyDonutModeUI(donutMode);
      loadInitial();
    });
  };
})();
