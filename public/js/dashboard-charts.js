/* ApexCharts helpers
   - Exposes window.initDashboardCharts(data)
   - Safe if containers are missing
   - Waits for ApexCharts to be available
*/
(function(){
  function ready(fn){ if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  function waitForApex(cb){
    if (window.ApexCharts) return cb();
    var tries = 0; var t = setInterval(function(){
      if (window.ApexCharts || ++tries > 100) { clearInterval(t); cb(); }
    }, 50);
  }

  function initDonut(el, data){
    if(!el || !window.ApexCharts) return;
    var series = (data && data.series) || [60, 40];
    var labels = (data && data.labels) || ['A','B'];
    var chart = new ApexCharts(el, {
      chart: { type: 'donut', height: el.clientHeight || 320, animations: {enabled: true} },
      series: series,
      labels: labels,
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      stroke: { width: 1 },
      colors: ['#2563eb', '#94a3b8']
    });
    chart.render();
  }

  function initBar(el, data){
    if(!el || !window.ApexCharts) return;
    var categories = (data && data.categories) || [];
    var forecast = (data && data.forecast) || [];
    var actual = (data && data.actual) || [];
    var chart = new ApexCharts(el, {
      chart: { type: 'bar', height: el.clientHeight || 320, stacked: false, animations: {enabled:true} },
      series: [
        { name: 'Forecast', data: forecast },
        { name: 'Actual', data: actual },
      ],
      xaxis: { categories: categories },
      plotOptions: { bar: { columnWidth: '45%', endingShape: 'rounded' } },
      dataLabels: { enabled: false },
      colors: ['#60a5fa','#10b981'],
      legend: { position: 'bottom' }
    });
    chart.render();
  }

  window.initDashboardCharts = function(data){
    data = data || (window.dashboardChartData || {});
    ready(function(){
      var donutEl = document.getElementById('donutQuota');
      var barEl = document.getElementById('barForecastActual');
      // If neither container exists, silently return
      if(!donutEl && !barEl) return;

      function start(){
        if (donutEl) initDonut(donutEl, data.donut);
        if (barEl) initBar(barEl, data.bar);
      }
      waitForApex(start);
    });
  };

  // Auto-call with global data if present (non-fatal)
  if (typeof window !== 'undefined') {
    ready(function(){
      if (window.dashboardChartData) {
        window.initDashboardCharts(window.dashboardChartData);
      }
    });
  }
})();
