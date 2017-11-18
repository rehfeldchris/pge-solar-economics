# What is it?

This is just a quick n dirty script to help me calculate whether or not 
buying solar panels would make financial sense. 

# Why not use one of the tons of existing calculators on the web?
They don't attempt to accurately model
solar systems that only partially replace your consumption. I feel this use case is important
because many utilities charge you using a tiered usage strategy, where you pay more per KWH 
as your monthly consumption increases, and/or a time-of-use strategy, where you pay more during 
certain hours of the day, or days of the week. This can make things really complicated to calculate, so
a more complicated calculator is needed. Due to the tiered and time-of-use factors, the bill reduction 
by adding the first panel tends to be larger than the bill reduction of the 2nd panel. This is because the cost per KWH is higher in
 the higher tiers, and the first panel you add helps get you back to lower tier pricing. Eventually, after adding enough panels,
 your monthly bill will be all tier 1 pricing, and at that point, the marginal value of adding another solar panel
 is much lower, because tier 1 pricing tends to be low.
 
 Also, I find many calculators on the web tend to be overly optimistic in favor of solar. 

# How does it work?

PG&E lets you download your electric usage history by the hour, so you can see how many KWH you
 used for any hour of any day in the past, which is pretty sweet. I then attempt to 
recreate their stupid-complicated pricing formula (tier 1, tier 2, peak hours, 
part-peak, off-peak, weekdays vs weekends) blah blah... With that, I run the hourly usage data
through the recreation of their pricing strategy, which gives me a price per 
month figure that matches my actual bill reasonably close.
 
Then, I run the numbers again, but this time I offset the power usage to simulate the
 effects of solar panels running. Eg, If I'm modeling a single 250 watt panel, then 
 during peak daylight hours I subtract 250 watts or so, each hour from whatever PG&E said my KWH
 was that hour. Then using the pricing strategy, I recompute how much my montly bill would be, and compare it to
 the non-solar bill to see what the savings would be.
  
Regarding the solar power production - I try to be accurate here, using a database that lets me calculate sunrise 
 and sunset for my location via lat/lon coordinates for each date of the year. Also, since the solar panels would get 
 very little solar radiation in the first hours after sunrise, and thus produce very little power during that time,
  I account for this and model a reduced output of the panel for a few hours, gradually achieving full output, 
  then ramping down again as sunset approaches for that day.


# So, what was the result?
I'm currently a pretty light electricity user, so my bill is usually small. The cost of real roof mounted multi-panel solar
doesn't make sense for me. Depending on the system, and installation fees, I usually get a payback
time of 12-18 years. That's not a good investment for my case, as stocks and bonds
 offer far superior annual returns. However, the economics of a single 250 watt panel, such as the plug-n-play 
 ones which just sit on the ground in your backyard, and plug into a wall outlet, do make sense, because it costs
 in the $500'ish range, with no install fees. 