School Club Tracking

This is only on GitHub as a code sample for job applications, it's quick and dirty.  Who knows, maybe someone can use this code but it is not OOPified.

-It authenticates using wordpress and it's cookie.  This page is contained within a standard wordpress site updated by school volunteers.

-It connects to a mysql database containing all club registrations and staff contact info generated from another club signup application.  An option up refresh with a CSV dump from the signup application is included.

-It cross references the registrations against a google calendar containing club dates and IDs.  It is hosted on google calendar so that it can be easily maintained by school volunteers.

-It emails each staff member a list of of children attending clubs on a selected day.  It also emails the teacher that the child will be dismissed to a club.

-It generates a PDF with club and contact information for each child that exactly fits a sheet of Avery Label stickers.  Stickers are applied to each child as they arrive.