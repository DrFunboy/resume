var calendar = CalendarApp.getCalendarById("XXX"),
sheet = SpreadsheetApp.getActiveSheet();

function doPost(e){
  var postdata = JSON.parse(e.postData.contents);
  if (postdata.mode == "delete") {
    var d1 = new Date(postdata.start),
    d2 = new Date(postdata.end),
    event = calendar.getEvents(d1, d2, {
      search: postdata.name
    }
    )[0];
    event.deleteEvent();
  } else {
    var event = calendar.createEvent(postdata.summary, new Date(postdata.start), new Date(postdata.end), postdata.opts);
    return ContentService.createTextOutput(event.getId());
  }
}

function doGet(){
  return ContentService.createTextOutput(JSON.stringify(fetchEvents()));
}

function fetchEvents() {
  var d1 = new Date(new Date().getTime()+1000*60*60*2),
  d2 = new Date(new Date().getTime()+1000*60*60*24*20),
  events = {};
  var list = calendar.getEvents(d1, d2);

  list.forEach(function(v){
    var datestart = Utilities.formatDate(v.getStartTime(), "GMT", "yyyy-MM-dd HH:mm:ss");
    events[datestart] = {
      name: v.getTitle()
    };
  })

  return events;
}