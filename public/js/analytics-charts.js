/** Analytics Charts
 * Fetches JSON from /analytics/data and renders bar + donut charts
 * Guards ApexCharts availability and missing containers gracefully
 */
(function(){
  function ready(fn){ if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  function q(id){ return document.getElementById(id); }

  function renderBar(el, data){ if(!el || !window.ApexCharts) return; new ApexCharts(el, {
      chart:{ type:'bar', height: el.clientHeight || 320, animations:{enabled:true} },
      series: data.series || [],
      xaxis: { categories: data.categories || [] },
      plotOptions: { bar: { columnWidth: '50%', endingShape: 'rounded' } },
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      colors: ['#60a5fa','#2563eb']
  }).render(); }

  function renderDonut(el, data){ if(!el || !window.ApexCharts) return; new ApexCharts(el, {
      chart:{ type:'donut', height: el.clientHeight || 320, animations:{enabled:true} },
      series: (data && data.series) || [0,0],
      labels: (data && data.labels) || ['Actual','Remaining'],
      legend: { position: 'bottom' },
      dataLabels: { enabled:false },
      colors: ['#2563eb','#94a3b8']
  }).render(); }

  function fillTable(tbodyId, rows){
    var tb = q(tbodyId); if(!tb) return;
    if(!rows || !rows.length){ tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>'; return; }
    tb.innerHTML = rows.map(function(r){
      return '<tr>'+
        '<td>'+ (r.quota_number||'') +'</td>'+
        '<td>'+ (r.range_pk||'') +'</td>'+
        '<td class="text-end">'+ (Number(r.initial_quota)||0).toLocaleString() +'</td>'+
        '<td class="text-end">'+ (Number(r.forecast)||0).toLocaleString() +'</td>'+
        '<td class="text-end">'+ (Number(r.actual)||0).toLocaleString() +'</td>'+
        '<td class="text-end">'+ (Number(r.actual_pct)||0).toFixed(2) +'%</td>'+
      '</tr>';
    }).join('');
  }

  window.initAnalyticsCharts = function(cfg){
    ready(function(){
      var barEl = q(cfg.barElId || 'analyticsBar');
      var donutEl = q(cfg.donutElId || 'analyticsDonut');
      var dataUrl = cfg.dataUrl;
      var tbodyId = cfg.tableBodyId || 'analyticsTableBody';
      if(!dataUrl){ fillTable(tbodyId, []); return; }

      fetch(dataUrl, { headers: { 'Accept':'application/json' }})
        .then(function(r){ return r.json(); })
        .then(function(json){
          try{ renderBar(barEl, json.bar || {}); }catch(e){}
          try{ renderDonut(donutEl, json.donut || {}); }catch(e){}
          try{ fillTable(tbodyId, json.table || []); }catch(e){}
        })
        .catch(function(){ fillTable(tbodyId, []); });
    });
  };
})();

