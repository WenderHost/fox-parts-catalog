/* Reps and Distributors */
(function($){
  var config{{count}} = {
    pageLength: 50,
    lengthMenu: [25,50,100,200],
    ajax: {
      dataSrc: wpvars{{count}}.apiProperty,
      url: wpvars{{count}}.apiUrl
    },
    order: [[0,'asc']],
    columns: [
      {
        data: function(row,type,set,meta){
          switch( wpvars{{count}}.type ){
            case 'state':
              return row.state;
              break;

            case 'country':
              return row.country;
              break;

            case 'distributors':
              return row.region;
              break;
          }
        },
        className: 'w25'
      }
    ]
  };

  if( wpvars{{count}}.type == 'distributors' ){
    config{{count}}.columns.push({
      data: function(row,type,set,meta){
        if( typeof row.image != 'undefined' ){
          return `<a href="${row.link}" target="_blank"><img src="${row.image}" style="max-width: 120px;" /></a>`;
        } else {
          return `&nbsp;`;
        }
      }
    });
    config{{count}}.columns.push({
      data: function(row,type,set,meta){
        return `<a href="${row.link}" target="_blank">${row.distributor}</a>`;
      }
    });
  }

  if( wpvars{{count}}.type == 'state' || wpvars{{count}}.type == 'country' ){
    config{{count}}.columns.push({
      data: function(row,type,set,meta){
        return `<a href="${row.link}" target="_blank">${row.representative}</a>`;
      }
    });
    config{{count}}.columns.push({
      data: "contactInfo",
      className: 'nowrap'
    });
    config{{count}}.columns.push({
      data: function(row,type,set,meta){
        var info = '';
        if( typeof row.moreInfo != 'undefined' )
          info = row.moreInfo;
        return info;
      }
    });
  }

  $(`#${wpvars{{count}}.type}`).DataTable(config{{count}});
})(jQuery);