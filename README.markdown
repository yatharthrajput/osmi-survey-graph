# 2016 OSMI Survey Graph

## Installing and Running

* Copy `.env.example`
* Update settings as appropriate for your environment
* Ensure an instance of Neo4j 3.x is running (Bolt protocol) with the GraphAware UUID plugin (see below)
* Run `./scipts/load-data.php`
* Start the web server: `./server.sh`
* Visit [http://localhost:8888/browser/browser.html](http://localhost:8888/browser/browser.html)
* Explore the API using the HAL browser

### Installing the GraphAware UUID Plugin

* Download the [GraphAware UUID](http://products.graphaware.com/download/uuid/latest) component
* Download the [GraphAware Framework](http://products.graphaware.com/download/framework-server-community/latest)
* Copy both jars into `/path/to/neo4j/plugins`
* Add the following the the bottom of your `/path/to/neo4j/conf/neo4j.conf` and restart Neo4j

```
com.graphaware.runtime.enabled=true
com.graphaware.module.UIDM.1=com.graphaware.module.uuid.UuidBootstrapper
com.graphaware.module.UIDM.uuidProperty=uuid
```

### Updating the dataset

* Make sure you have a Typeform API Key, and add it to your .env file as `TYPEFORM_API_KEY=XXXX`
* Run `./srcipts/clear-and-reload-data.sh`

## API Endpoints

These are very rough notes describing the desired functionality of the API.
Either delete after implementation or update.

- [x] Get all questions (see /questions)
- [x] Get all respondents (see /respondents)
- [x] Get answers based on how people answered question X (find answer, follow `respondents` rel, all respondents have and `answers` rel)
- [x] Here's the question, and I want to get the answers for that question (/questions/{uuid} endpoint returns specified question, `Answers` are linked and embedded)
    - [ ] Optionally you can pass clauses to filter (not implemented)
    - [ ] Response counts based on demographic data for the users who responded (no demographic endpoints are implemented yet)
    - [ ] "What I am imagine is a tool where you could select certain demographics about users, and then see how they responded to certain questions"
    - [x] "You also might have the capability of saying if they answered this question with this answer, how did they answer another question" (/questions -> answers -> respondents -> respondent)

## Consuming the API: Quick Tips

Hypermedia APIs can be "chatty" due to the need to make requests across multiple rels. ETags are provided to aid in caching. Please see the [Some background for those not familiar with using Hypermedia APIs](https://api.foxycart.com/docs) section of the FoxyCart docs for some high level info about utilizing caching via ETags to efficiently consume hypermedia APIs:

> One of the keys to a successful Hypermedia API is proper use of caching (all clients should take advantage of local caches and ETags). If you’re not familiar with hypermedia APIs, things may seem a bit “chatty” at first, but that’s where your client’s local cache comes into play.
>
> Start with the home page and cache it. On the next request (to that or any resource you've already visited), be sure to send that ETag along with an If-None-Match header so you can use your cache entry in the event of a 304 Not Modified response. From there, use hypermedia to navigate to related resources using the links either in the header or the body of the response.
>
> Unlike RPC (remote procedure call) APIs, you shouldn't code to the individual endpoints. We (the server) control those and should be able to change them as needed. So don’t code to the URLs. You should code to the link “rel” tags which define the relationships to the current resource. These should not change, though we may introduce new links (and new rels) at any time, so don’t assume they will be in a specific order.

## Grabbing data from Typeform

Example using `httpie`. **We should write a script to do this.**
```
http GET https://api.typeform.com/v1/form/Ao6BTw key==<TYPEFORM_API_KEY> completed==true offset==0 > ~/osmi-survey-2016_0000.json
```

## Sample Cypher Queries

Single response
```
MATCH (q:Question { id: "list_18065482_choice" })-[:HAS_ANSWER]->(a)<-[:ANSWERED]-(p)
RETURN q.question AS question, a.answer AS answer, COUNT(*) AS responses
ORDER BY responses DESC;
```

Paged responses
```
MATCH (q:Question)
WITH q
ORDER BY q.order
SKIP 0
LIMIT 10
MATCH (q)-[:HAS_ANSWER]->(a)<-[:ANSWERED]-(p)
RETURN q.order, q.question, a.answer, COUNT(*) AS responses
ORDER BY q.order, responses DESC;
```

Who works somewhere other than they live, and where are those places?
```
MATCH (works)<-[:WORKS_IN]-(person:Person)-[:LIVES_IN]->(lives)
WHERE works <> lives
RETURN person, works, lives;
```


## More example queries

```
// List of questions
MATCH (q:Question)
RETURN q ORDER BY q.order ASC

// A single question: "Have you ever sought treatment for a mental health issue from a mental health professional?"
MATCH (q:Question {field_id: 18068717})
RETURN q

// Question and all answers
MATCH (q:Question {field_id: 18068717})-[:HAS_ANSWER]->(a:Answer)
RETURN q, a

// Persons who have answered
MATCH (q:Question {field_id: 18068717})-[:HAS_ANSWER]->(a:Answer)<-[:ANSWERED]-(p:Person)
RETURN a, p

// Answer counts
MATCH (q:Question {field_id: 18068717})-[:HAS_ANSWER]->(a:Answer)<-[:ANSWERED]-(p:Person)
RETURN a, COUNT(p)


// Answer counts broken out by country
MATCH (q:Question {field_id: 18068717})-[:HAS_ANSWER]->(a:Answer)<-[:ANSWERED]-(p:Person)-[:LIVES_IN_COUNTRY]->(c:Country)
RETURN q.question, a.answer, c.name, count(p) as count_country_answer
ORDER BY c.name

// Answer counts with percentages
MATCH (q:Question {field_id: 18068717})
WITH q
MATCH (q)-[:HAS_ANSWER]->()<-[ra:ANSWERED]-(p)-[:LIVES_IN_COUNTRY]-(c:Country)
WITH q, c, count(ra) as country_count
MATCH (q)-[:HAS_ANSWER]->(a:Answer)<-[:ANSWERED]-(p:Person)-[:LIVES_IN_COUNTRY]-(c)
WITH q, a, c, count(p) as answer_count, country_count
RETURN q.question, a.answer, c.name, answer_count, country_count, toString(((toFloat(answer_count) / toFloat(country_count))*100)) as perc
ORDER BY c.name ASC

// Top 10 self diagnoses WITHOUT a corresponding professional diagnosis
MATCH (selfDiagnosis:Disorder)<-[:SELF_DIAGNOSIS]-(p:Person)
WHERE NOT (p)-[:PROFESSIONAL_DIAGNOSIS]->()
RETURN selfDiagnosis.name, COUNT(p) AS diagnoses
ORDER BY diagnoses DESC
LIMIT 10;

// Top 10 Diagnoses: Self-diagnoses vs MD-diagnoses
MATCH (d:Disorder)<-[sd:SELF_DIAGNOSIS]-()
WITH d, COUNT(sd) AS selfDiagnoses
MATCH (d)<-[mdd:PROFESSIONAL_DIAGNOSIS]-()
WITH d, selfDiagnoses, COUNT(mdd) AS mdDiagnoses
RETURN d.name AS disorder, selfDiagnoses, mdDiagnoses
// Order by selfDiagnoses or mdDiagnoses, depending on preference
ORDER BY selfDiagnoses DESC
LIMIT 10;

// Incidence of self-diagnoses (WITHOUT corresponding MD diagnosis) compared to available 
// employer provided mental health coverage
// JOINs: Question to Answer.
// If ANSWERED, SELF_DIAGNOSIS, and PROFESSIONAL_DIAGNOSIS all represent pivot tables, then:
//  * Question -> Answer
//  * Person -> PersonAnswer -> Answer
//  * Person -> PersonSelfDiagnosis -> Diagnosis
//  * Person -> PersonProfessionalDiagnosis -> Diagnosis
// PROFESSIONAL_DIAGNOSIS rel would also likely represent a pivot table
MATCH (q:Question { field_id: 18065507 })-[:HAS_ANSWER]->(a)<-[:ANSWERED]-(p)-[sd:SELF_DIAGNOSIS]->(d)
WHERE NOT (p)-[:PROFESSIONAL_DIAGNOSIS]->()
RETURN a.answer, COUNT(sd) AS selfDiagnoses
ORDER BY selfDiagnoses DESC
LIMIT 10;

```
