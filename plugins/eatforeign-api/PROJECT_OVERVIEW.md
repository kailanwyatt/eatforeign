# EatForeign: Project Overview

**EatForeign** is an intelligent, automated platform designed to help users explore and celebrate global culinary traditions. The platform serves as a vibrant, interconnected encyclopedia of world cuisines, linking national dishes to the specific countries they originate from and the holidays during which they are traditionally enjoyed.

---

## 1. The Core Data Engine

At the heart of EatForeign is a highly automated, AI-driven content generation pipeline. Instead of relying on manual data entry, the platform uses Google Gemini to intelligently scrape, structure, and publish rich content from around the web.

### The Automated Pipeline
1. **Source Scraping:** Administrators can input URLs containing lists of national dishes or global food holidays (e.g., Wikipedia, World Population Review).
2. **Intelligent Extraction:** The system strips the raw text and sends it to Gemini, which reads the unstructured data and cleanly extracts the names, dates, and countries into a **Pending Items** queue.
3. **Deep Content Generation:** A daily background job processes this queue. It passes each item back to Gemini to write a comprehensive, factual article. Gemini populates deep metadata (history, ingredients, recipes), while a Google Custom Search integration automatically fetches real-world imagery.
4. **Editorial Review:** The generated content is saved as a **Draft** in WordPress, neatly categorized with taxonomies like Spice Level, Dietary Type, and Cuisine, ready for final human review.

### The Interconnected Structure
The platform is built on three deeply interconnected Custom Post Types (CPTs):
* **Dishes:** The specific food item (e.g., *Ackee and Saltfish*).
* **Countries:** Hub pages that aggregate all the dishes belonging to a specific region (e.g., *Jamaica 🇯🇲*).
* **Celebrations:** Holidays and observances (e.g., *Jamaican Independence Day* or *National Pizza Day*).

Whenever a Dish is generated, the system automatically creates or updates the associated Country and Celebration, building a massive web of cross-linked culinary data.

---

## 2. User Engagement & Notifications (Upcoming Feature)

The ultimate goal of EatForeign isn't just to be an encyclopedia—it's to be an active, daily companion that encourages users to expand their culinary horizons. 

To achieve this, EatForeign will implement a proactive **Notification Engine** designed to alert users about timely culinary events.

### How Notifications Work
Users will be able to opt-in to notifications (via Email newsletters, Push Notifications, or an In-App feed) to receive timely alerts based on the platform's vast calendar of `ef_celebration` dates.

#### "What National Day is it?"
If today is *National Donut Day* or *World Paella Day*, users will receive a morning notification:
> 🗓️ **Today is World Paella Day!** 
> Discover the rich history of Spain's iconic dish and find authentic recipes to celebrate tonight.

#### Upcoming Independence Days
The system will look ahead at the calendar and notify users a few days before a major national holiday, giving them time to shop for ingredients or make restaurant reservations.
> 🎉 **Jamaican Independence Day is this Friday!**
> Get ready to celebrate. Check out the traditional recipe for *Ackee and Saltfish*, or find a local Caribbean restaurant near you.

#### Personalized Recommendations
Because all dishes are heavily tagged (Vegan, Mild Spice, Seafood, etc.), the notification engine will eventually be able to send personalized alerts. If a user is subscribed to "Vegan" content, they might receive:
> 🌱 **National Dumpling Day is tomorrow!**
> Celebrate with this completely plant-based recipe for *Korean Yachae Mandu (Vegetable Dumplings)*.

### The Value Loop
By combining the **automated AI content pipeline** with a **timely notification engine**, EatForeign creates a self-sustaining loop. The AI constantly scours the web to populate the calendar with new holidays and dishes, and the notification engine uses that calendar to continuously re-engage users, prompting them to cook, explore, and "Eat Foreign" every single day.
