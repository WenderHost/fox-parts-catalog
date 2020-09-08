/* Product Change Notices Initialization */
(function($){
  $('#product-change-notices').DataTable({
    pageLength: 50,
    lengthMenu: [25,50,100,200],
    ajax: {
      dataSrc: 'pcNs',
      url: 'https://api.sheety.co/202a6b1f472c208bf6c05ad5dce78066/foxProductChangeNotices/pcNs'
    },
    order: [[1,'desc']],
    columns: [
      {
        data: "pcnNo",
        width: '10%',
        className: 'align-right'
      },
      {
        data: "datePosted",
        type: 'date',
        width: '10%',
        className: 'align-right'
      },
      {
        data: "typeOfChange",
        width: '20%'
      },
      {
        data: "productsAffected",
        width: '50%'
      },
      {
        data: function(row,type,set,meta){
          var link = `<a href="${row.viewPcn}" target="_blank">View PCN</a>`;
          return link;
        },
        width: '10%',
        className: 'align-center'
      },
      {
        data: "id",
        visible: false
      }
    ]
  });
})(jQuery);

/*

 */